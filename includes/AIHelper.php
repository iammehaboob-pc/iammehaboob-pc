<?php
/**
 * SmartFix AI - AI Helper Class
 * Handles integration with Google Gemini API and database similarity duplicate check.
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access not permitted.");
}

class AIHelper {
    
    // Check if a similar active complaint already exists
    public static function checkDuplicates(string $title, string $description): ?int {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT id, title, description FROM complaints WHERE status NOT IN ('resolved', 'rejected')");
            $complaints = $stmt->fetchAll();
            
            $titleLower = strtolower(trim($title));
            $descLower = strtolower(trim($description));

            foreach ($complaints as $comp) {
                $existingTitle = strtolower($comp['title']);
                $existingDesc = strtolower($comp['description']);
                
                // Compare titles using similarity percentage
                similar_text($titleLower, $existingTitle, $titlePerc);
                if ($titlePerc > 65) {
                    return (int)$comp['id'];
                }
                
                // Compare descriptions (if long enough)
                if (strlen($descLower) > 20 && strlen($existingDesc) > 20) {
                    similar_text($descLower, $existingDesc, $descPerc);
                    if ($descPerc > 70) {
                        return (int)$comp['id'];
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Duplicate checking query failed: " . $e->getMessage());
        }
        return null;
    }

    // Analyze complaint details (via Gemini or Local Rule-based Rule Fallback)
    public static function analyzeComplaint(string $title, string $description): array {
        // Fetch valid categories and departments to validate AI results
        $categoriesList = ['Network/Wi-Fi Issues', 'Electrical Fault', 'Water Leakage', 'Broken Furniture', 'Waste/Cleaning', 'Smartboard Malfunction'];
        $departmentsList = ['IT Support & Computer Labs', 'Electrical Maintenance', 'Plumbing & Sanitation', 'Carpentry & Furniture', 'Housekeeping & Campus Cleaning', 'Academic Blocks & Classrooms'];
        
        $apiKey = '';
        try {
            $db = Database::getInstance()->getConnection();
            $keyStmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key' LIMIT 1");
            $apiKey = $keyStmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Failed to fetch gemini api key from settings: " . $e->getMessage());
        }

        if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
            $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
        }
        
        if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
            // Graceful Mock Fallback Analyzer
            return self::localKeywordAnalyzer($title, $description, $categoriesList, $departmentsList);
        }

        // Call Google Gemini API
        try {
            $url = GEMINI_API_URL . "?key=" . $apiKey;
            
            $prompt = "You are an automated campus ticket triage system. Analyze this complaint:\n";
            $prompt .= "Title: " . $title . "\n";
            $prompt .= "Description: " . $description . "\n\n";
            $prompt .= "Categorize the complaint into EXACTLY one of these categories: " . implode(', ', $categoriesList) . "\n";
            $prompt .= "Recommend EXACTLY one department to resolve it: " . implode(', ', $departmentsList) . "\n";
            $prompt .= "Assign a priority level: 'low', 'medium', 'high', or 'urgent' based on urgency, health and safety, or SLA implications.\n";
            $prompt .= "Suggest a brief, helpful possible solution or troubleshooting tip for the user.\n";
            $prompt .= "Determine your confidence score (0.00 to 1.00).\n\n";
            $prompt .= "Return ONLY a valid JSON object with the keys 'category', 'department', 'priority', 'solution', and 'confidence'. Do not wrap the JSON in ```json markdown formatting.";

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $responseArr = json_decode($response, true);
                $aiText = $responseArr['candidates'][0]['content']['parts'][0]['text'] ?? '';
                
                // Clean markdown tags if the model added them
                $aiText = trim($aiText);
                if (strpos($aiText, '```') === 0) {
                    $aiText = preg_replace('/^```(?:json)?\n?|```$/i', '', $aiText);
                }
                
                $aiData = json_decode(trim($aiText), true);

                if ($aiData && isset($aiData['category'], $aiData['priority'])) {
                    // Match category and department
                    $matchedCategory = self::matchNearest($aiData['category'], $categoriesList);
                    $matchedDept = self::matchNearest($aiData['department'] ?? '', $departmentsList);
                    
                    return [
                        'category' => $matchedCategory,
                        'department' => $matchedDept,
                        'priority' => in_array(strtolower($aiData['priority']), ['low', 'medium', 'high', 'urgent']) ? strtolower($aiData['priority']) : 'medium',
                        'solution' => Security::sanitizeString($aiData['solution'] ?? 'No suggested solution available.'),
                        'confidence' => (float)($aiData['confidence'] ?? 0.85),
                        'is_mock' => false
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Gemini API call failed: " . $e->getMessage());
        }

        // Fallback to keyword analyzer if API call fails
        return self::localKeywordAnalyzer($title, $description, $categoriesList, $departmentsList);
    }

    // Match output using string distances for safety
    private static function matchNearest(string $input, array $options): string {
        $bestMatch = $options[0];
        $shortest = -1;

        foreach ($options as $opt) {
            $lev = levenshtein(strtolower($input), strtolower($opt));
            if ($lev === 0) return $opt;
            if ($lev < $shortest || $shortest < 0) {
                $bestMatch = $opt;
                $shortest = $lev;
            }
        }
        return $bestMatch;
    }

    // High fidelity offline keyword matching fallback
    private static function localKeywordAnalyzer(string $title, string $description, array $categories, array $departments): array {
        $text = strtolower($title . ' ' . $description);
        
        $category = 'Network/Wi-Fi Issues';
        $dept = 'IT Support & Computer Labs';
        $priority = 'medium';
        $solution = 'Check if other students are facing the same issue and ensure your network credentials are correct.';
        $confidence = 0.60;

        if (self::containsAny($text, ['wifi', 'wi-fi', 'internet', 'network', 'router', 'connection', 'portal'])) {
            $category = 'Network/Wi-Fi Issues';
            $dept = 'IT Support & Computer Labs';
            $solution = 'Try restarting your device Wi-Fi connection or reconnecting to the campus portal. Check if the router is powered.';
        } elseif (self::containsAny($text, ['light', 'fan', 'switch', 'wire', 'power', 'socket', 'plug', 'fuse', 'electricity', 'shock'])) {
            $category = 'Electrical Fault';
            $dept = 'Electrical Maintenance';
            $solution = 'Avoid touching the faulty outlet or switch. Maintenance will check the wiring connections and replace any burnt parts.';
            if (strpos($text, 'shock') !== false || strpos($text, 'spark') !== false) {
                $priority = 'urgent';
            } else {
                $priority = 'high';
            }
        } elseif (self::containsAny($text, ['leak', 'water', 'pipe', 'tap', 'clog', 'toilet', 'flush', 'drain', 'overflow'])) {
            $category = 'Water Leakage';
            $dept = 'Plumbing & Sanitation';
            $solution = 'Turn off the localized shutoff valve if possible. Maintenance will inspect the pipe joints or clear the clog.';
            $priority = 'medium';
        } elseif (self::containsAny($text, ['chair', 'desk', 'bench', 'table', 'furniture', 'door', 'handle', 'hinge', 'board'])) {
            $category = 'Broken Furniture';
            $dept = 'Carpentry & Furniture';
            $solution = 'Avoid utilizing the damaged furniture to prevent injuries. The carpenter will inspect and secure the joints or replace the item.';
            $priority = 'low';
        } elseif (self::containsAny($text, ['trash', 'garbage', 'bin', 'clean', 'dirt', 'dust', 'sweep', 'wash', 'smell', 'odor'])) {
            $category = 'Waste/Cleaning';
            $dept = 'Housekeeping & Campus Cleaning';
            $solution = 'Housekeeping has been notified to clean the area and empty the containers immediately.';
            $priority = 'low';
        } elseif (self::containsAny($text, ['projector', 'smartboard', 'screen', 'stylus', 'hdmi'])) {
            $category = 'Smartboard Malfunction';
            $dept = 'Academic Blocks & Classrooms';
            $solution = 'Ensure the HDMI/VGA cables are connected securely. Try power-cycling the projector using the remote controller.';
            $priority = 'medium';
        }

        // Safety override for urgent matters
        if (self::containsAny($text, ['fire', 'smoke', 'shock', 'short circuit', 'burst pipe', 'flooding'])) {
            $priority = 'urgent';
            $confidence = 0.90;
        }

        return [
            'category' => $category,
            'department' => $dept,
            'priority' => $priority,
            'solution' => $solution,
            'confidence' => $confidence,
            'is_mock' => true
        ];
    }

    private static function containsAny(string $haystack, array $needles): bool {
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
