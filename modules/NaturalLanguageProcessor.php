<?php
/**
 * Natural Language Processor for Incident Reports
 * 
 * @author Law Enforcement System
 * @version 1.0.0
 */

class NaturalLanguageProcessor {
    public static function analyzeText($text, array $context = []) {
        require_once __DIR__ . '/CloudNLPService.php';

        $service = new CloudNLPService();
        return $service->analyze($text, $context);
    }

    
    // Sentiment and emotion keywords
    private static $positive_keywords = [
        'good', 'great', 'excellent', 'safe', 'helped', 'rescued', 'saved', 
        'calm', 'peaceful', 'resolved', 'happy', 'satisfied', 'cooperative'
    ];
    
    private static $negative_keywords = [
        'bad', 'terrible', 'awful', 'dangerous', 'violent', 'harm', 'hurt', 
        'angry', 'upset', 'crying', 'panic', 'fear', 'scared', 'threat',
        'damage', 'destroyed', 'stolen', 'missing', 'lost'
    ];
    
    // Severity indicators
    private static $critical_keywords = [
        'critical', 'emergency', 'immediate', 'life-threatening', 'severe', 'dead', 
        'died', 'unconscious', 'bleeding', 'gun', 'knife', 'weapon', 'murder', 'kill',
        'rape', 'sexual assault', 'kidnap', 'abduction'
    ];
    
    // Emotion indicators
    private static $emotion_keywords = [
        'scared' => 'Fear',
        'terrified' => 'Fear',
        'angry' => 'Anger',
        'furious' => 'Anger',
        'sad' => 'Sadness',
        'depressed' => 'Sadness',
        'happy' => 'Happiness',
        'relieved' => 'Relief',
        'confused' => 'Confusion',
        'traumatized' => 'Trauma',
        'distressed' => 'Distress'
    ];
    
    // Victim descriptors for entity extraction
    private static $victim_descriptors = [
        'victim', 'child', 'woman', 'man', 'girl', 'boy', 'elderly', 
        'senior', 'minor', 'infant', 'baby', 'person'
    ];
    
    // Action verbs for incident analysis
    private static $action_verbs = [
        'hit', 'punch', 'kick', 'slap', 'push', 'grab', 'strangle', 'choke',
        'stab', 'shoot', 'throw', 'hurt', 'injure', 'beat', 'assault',
        'steal', 'rob', 'burglarize', 'vandalize', 'damage', 'destroy',
        'rape', 'abuse', 'neglect', 'abandon', 'threaten', 'intimidate'
    ];
    
    /**
     * Analyze incident text for comprehensive insights
     * 
     * @param string $narrative The incident narrative text
     * @param string $incident_type The selected incident type
     * @return array Comprehensive analysis results
     */
    public static function analyzeIncident($narrative, $incident_type = '') {
        $narrative_lower = strtolower($narrative);
        
        return [
            'sentiment' => self::analyzeSentiment($narrative_lower),
            'emotions' => self::detectEmotions($narrative_lower),
            'severity_score' => self::calculateSeverityScore($narrative_lower, $incident_type),
            'key_phrases' => self::extractKeyPhrases($narrative),
            'entities' => self::extractEntities($narrative),
            'threat_level' => self::determineThreatLevel($narrative_lower, $incident_type),
            'actionable_items' => self::extractActionableItems($narrative),
            'word_count' => str_word_count($narrative),
            'text_quality' => self::assessTextQuality($narrative),
            'confidence_score' => self::calculateConfidenceScore($narrative)
        ];
    }
    
    /**
     * Analyze sentiment of the narrative
     * Returns: positive, negative, neutral
     */
    private static function analyzeSentiment($text_lower) {
        $positive_count = 0;
        $negative_count = 0;
        
        foreach (self::$positive_keywords as $keyword) {
            $positive_count += substr_count($text_lower, $keyword);
        }
        
        foreach (self::$negative_keywords as $keyword) {
            $negative_count += substr_count($text_lower, $keyword);
        }
        
        if ($negative_count > $positive_count * 2) {
            return ['sentiment' => 'Negative', 'score' => $negative_count];
        } elseif ($positive_count > $negative_count) {
            return ['sentiment' => 'Positive', 'score' => $positive_count];
        }
        
        return ['sentiment' => 'Neutral', 'score' => 0];
    }
    
    /**
     * Detect emotions expressed in the narrative
     */
    private static function detectEmotions($text_lower) {
        $detected_emotions = [];
        
        foreach (self::$emotion_keywords as $keyword => $emotion) {
            if (strpos($text_lower, $keyword) !== false) {
                if (!in_array($emotion, $detected_emotions)) {
                    $detected_emotions[] = $emotion;
                }
            }
        }
        
        return !empty($detected_emotions) ? $detected_emotions : ['Neutral'];
    }
    
    /**
     * Calculate severity score based on keywords and context
     */
    private static function calculateSeverityScore($text_lower, $incident_type = '') {
        $score = 0;
        $max_score = 100;
        
        // Critical keywords (weight: 25 points each)
        foreach (self::$critical_keywords as $keyword) {
            if (strpos($text_lower, $keyword) !== false) {
                $score += 25;
            }
        }
        
        // Action verbs (weight: 10 points each)
        foreach (self::$action_verbs as $verb) {
            if (strpos($text_lower, $verb) !== false) {
                $score += 10;
            }
        }
        
        // Negative keywords (weight: 5 points each)
        foreach (self::$negative_keywords as $keyword) {
            if (strpos($text_lower, $keyword) !== false) {
                $score += 5;
            }
        }
        
        // Incident type modifier
        $type_modifiers = [
            'Violence' => 20,
            'Assault' => 20,
            'Murder' => 50,
            'Abuse' => 15,
            'Rape' => 30,
            'Neglect' => 10,
            'Theft' => 5,
            'Other' => 0
        ];
        
        if (isset($type_modifiers[$incident_type])) {
            $score += $type_modifiers[$incident_type];
        }
        
        // Cap the score at 100
        return min($score, $max_score);
    }
    
    /**
     * Extract key phrases from the narrative
     */
    private static function extractKeyPhrases($narrative) {
        // Split into sentences
        $sentences = preg_split('/[.!?]+/', $narrative, -1, PREG_SPLIT_NO_EMPTY);
        
        $phrases = [];
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            // Extract sentences with more than 5 words
            if (str_word_count($sentence) >= 5) {
                // Take the first 15 words as a phrase
                $words = explode(' ', $sentence);
                $phrase = implode(' ', array_slice($words, 0, min(15, count($words))));
                $phrases[] = trim($phrase);
            }
        }
        
        return array_slice(array_unique($phrases), 0, 5);
    }
    
    /**
     * Extract named entities (people, places, dates, objects)
     */
    private static function extractEntities($narrative) {
        $entities = [
            'people' => [],
            'locations' => [],
            'dates' => [],
            'items' => []
        ];
        
        // Simple pattern matching for capitalized words (potential names)
        preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/', $narrative, $matches);
        $potential_names = $matches[0];
        
        // Filter out common words
        $common_words = ['The', 'This', 'That', 'Was', 'Were', 'He', 'She', 'It', 'Officer', 'Police'];
        $entities['people'] = array_diff($potential_names, $common_words);
        
        // Extract dates
        preg_match_all('/\d{1,2}\/\d{1,2}\/\d{2,4}/', $narrative, $date_matches);
        $entities['dates'] = $date_matches[0];
        
        // Extract locations (capitalized multi-word phrases)
        preg_match_all('/(?:at|in|near|around)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)/i', $narrative, $location_matches);
        $entities['locations'] = isset($location_matches[1]) ? $location_matches[1] : [];
        
        return $entities;
    }
    
    /**
     * Determine threat level based on content analysis
     */
    private static function determineThreatLevel($text_lower, $incident_type = '') {
        $severity = self::calculateSeverityScore($text_lower, $incident_type);
        
        if ($severity >= 80) {
            return 'Critical';
        } elseif ($severity >= 60) {
            return 'High';
        } elseif ($severity >= 30) {
            return 'Medium';
        }
        
        return 'Low';
    }
    
    /**
     * Extract actionable items from the narrative
     */
    private static function extractActionableItems($narrative) {
        $actionable = [];
        
        // Look for patterns that indicate action items
        $patterns = [
            'victim needs' => '/victim\s+(?:needs?|requires?|should)\s+([^.!?]*)/i',
            'medical' => '/(?:medical|hospital|doctor|ambulance|injury)/i',
            'investigation' => '/(?:investigate|investigate|further action|follow-up)/i',
            'safety' => '/(?:safety|protection|safeguard|prevent)/i',
            'witnesses' => '/(?:witness|witnesses|saw|observed)/i'
        ];
        
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $narrative, $matches)) {
                $actionable[] = $key;
            }
        }
        
        return array_unique($actionable);
    }
    
    /**
     * Assess the quality and clarity of the text
     */
    private static function assessTextQuality($narrative) {
        $quality = [
            'is_detailed' => str_word_count($narrative) > 50,
            'has_timestamps' => preg_match('/\d{1,2}:\d{2}|AM|PM/i', $narrative) ? true : false,
            'has_locations' => preg_match('/\b(street|avenue|road|house|building|at|near|in)\b/i', $narrative) ? true : false,
            'has_specifics' => preg_match('/\b\d+\b/', $narrative) ? true : false,
            'grammar_score' => self::assessGrammar($narrative)
        ];
        
        return $quality;
    }
    
    /**
     * Simple grammar quality assessment
     */
    private static function assessGrammar($text) {
        $score = 100;
        
        // Check for common issues
        if (strlen($text) > 0) {
            // Missing punctuation
            $sentences = count(preg_split('/[.!?]+/', $text)) - 1;
            if ($sentences == 0) $score -= 20;
            
            // All caps (possible poor formatting)
            if (strtoupper($text) == $text) $score -= 15;
            
            // Too many exclamation marks
            if (substr_count(strtolower($text), '!') > 5) $score -= 10;
        }
        
        return max($score, 0);
    }
    
    /**
     * Calculate overall confidence score
     */
    private static function calculateConfidenceScore($narrative) {
        $score = 0;
        
        // Word count contributes 30%
        $word_count = str_word_count($narrative);
        $score += min($word_count / 2, 30);
        
        // Detail level contributes 30%
        if (strpos(strtolower($narrative), 'what') === false && 
            strpos(strtolower($narrative), 'why') === false &&
            strpos(strtolower($narrative), 'how') === false) {
            $score += 30;
        }
        
        // Punctuation/grammar contributes 20%
        if (preg_match('/[.!?]+/', $narrative)) {
            $score += 20;
        }
        
        // Presence of specific details contributes 20%
        if (preg_match('/\d+|[a-z]+@[a-z]+\.[a-z]+|09\d{9}/', strtolower($narrative))) {
            $score += 20;
        }
        
        return min($score, 100);
    }
    
    /**
     * Generate NLP summary for admin review
     */
    public static function generateNLPSummary($analysis) {
        $summary = [];
        
        $summary[] = "📊 **Analysis Summary**";
        $summary[] = "";
        $summary[] = "🎯 **Sentiment**: " . $analysis['sentiment']['sentiment'] . " (Score: {$analysis['sentiment']['score']})";
        $summary[] = "😢 **Emotions**: " . implode(', ', $analysis['emotions']);
        $summary[] = "⚠️ **Threat Level**: " . $analysis['threat_level'];
        $summary[] = "💯 **Severity**: " . number_format($analysis['severity_score'], 1) . "/100";
        $summary[] = "📝 **Confidence**: " . number_format($analysis['confidence_score'], 1) . "%";
        $summary[] = "";
        
        if (!empty($analysis['key_phrases'])) {
            $summary[] = "🔑 **Key Points**:";
            foreach ($analysis['key_phrases'] as $phrase) {
                $summary[] = "• " . $phrase;
            }
            $summary[] = "";
        }
        
        if (!empty($analysis['actionable_items'])) {
            $summary[] = "✅ **Recommended Actions**:";
            foreach ($analysis['actionable_items'] as $item) {
                $summary[] = "• " . ucfirst($item);
            }
            $summary[] = "";
        }
        
        if (!empty($analysis['entities']['people'])) {
            $summary[] = "👥 **People Mentioned**: " . implode(', ', array_slice($analysis['entities']['people'], 0, 3));
            $summary[] = "";
        }
        
        $summary[] = "📊 **Text Quality**:";
        $summary[] = "• Detailed: " . ($analysis['text_quality']['is_detailed'] ? 'Yes ✓' : 'Could be more detailed');
        $summary[] = "• Timestamps: " . ($analysis['text_quality']['has_timestamps'] ? 'Yes ✓' : 'No');
        $summary[] = "• Locations: " . ($analysis['text_quality']['has_locations'] ? 'Yes ✓' : 'No');
        
        return implode("\n", $summary);
    }
    
    /**
     * Suggest next steps based on analysis
     */
    public static function suggestNextSteps($analysis, $incident_type) {
        $steps = [];
        
        // Based on threat level
        if ($analysis['threat_level'] === 'Critical') {
            $steps[] = 'Escalate to Emergency Response Unit immediately';
            $steps[] = 'Contact Barangay Officials for urgent coordination';
            $steps[] = 'Dispatch nearest available officer';
        }
        
        // Based on emotions and trauma
        if (in_array('Trauma', $analysis['emotions']) || in_array('Fear', $analysis['emotions'])) {
            $steps[] = 'Consider counseling/victim support services';
            $steps[] = 'Follow up for psychological assessment';
        }
        
        // Based on actionable items
        if (in_array('medical', $analysis['actionable_items'])) {
            $steps[] = 'Arrange medical evaluation for victim';
            $steps[] = 'Document any injuries photographically';
        }
        
        if (in_array('witnesses', $analysis['actionable_items'])) {
            $steps[] = 'Identify and interview witnesses';
            $steps[] = 'Collect witness statements';
        }
        
        // Based on incident type
        if ($incident_type === 'Abuse' || $incident_type === 'Domestic') {
            $steps[] = 'Assess immediate safety of victim';
            $steps[] = 'Provide information on protective orders';
            $steps[] = 'Connect with domestic violence resources';
        }
        
        if ($incident_type === 'Theft') {
            $steps[] = 'Document all stolen items with descriptions';
            $steps[] = 'Check nearby pawn shops and markets';
            $steps[] = 'Review CCTV footage if available';
        }
        
        return array_unique($steps);
    }
}

?>
