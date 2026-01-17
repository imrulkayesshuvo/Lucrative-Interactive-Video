<?php
/**
 * Question Manager class
 */

if (!defined('ABSPATH')) {
    exit;
}

class LIVQ_Question_Manager {
    
    public function __construct() {
        // Question management methods are handled in the dashboard class
        // This class can be extended for additional question-related functionality
    }
    
    /**
     * Get all questions
     */
    public static function get_questions($type = null) {
        global $wpdb;
        
        $where = '';
        if ($type) {
            $where = $wpdb->prepare("WHERE type = %s", $type);
        }
        
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}livq_questions $where ORDER BY title");
    }
    
    /**
     * Get question by ID
     */
    public static function get_question($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}livq_questions WHERE id = %d", $id));
    }
    
    /**
     * Get questions by IDs
     */
    public static function get_questions_by_ids($ids) {
        if (empty($ids)) {
            return array();
        }
        
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}livq_questions WHERE id IN ($placeholders)", $ids));
    }
    
    /**
     * Validate question data
     */
    public static function validate_question_data($data) {
        $errors = array();
        
        if (empty($data['title'])) {
            $errors[] = 'Question title is required';
        }
        
        if (empty($data['type']) || !in_array($data['type'], array('true_false', 'multiple_choice'))) {
            $errors[] = 'Invalid question type';
        }
        
        if ($data['type'] === 'multiple_choice') {
            if (empty($data['options']) || count($data['options']) < 2) {
                $errors[] = 'Multiple choice questions must have at least 2 options';
            }
        }
        
        if (empty($data['correct_answer'])) {
            $errors[] = 'Correct answer is required';
        }
        
        return $errors;
    }
    
    /**
     * Format question for frontend display
     */
    public static function format_question_for_frontend($question) {
        // If type is empty, try to detect from correct_answer structure
        $detected_type = $question->type;
        if (empty($detected_type) && !empty($question->correct_answer)) {
            $decoded = json_decode($question->correct_answer, true);
            if (is_array($decoded) && !empty($decoded)) {
                $first_key = key($decoded);
                // Check if it's an associative array with URLs as keys (match_image_label)
                if (is_string($first_key) && (strpos($first_key, 'http://') === 0 || strpos($first_key, 'https://') === 0 || strpos($first_key, '/') === 0 || strpos(strtoupper($first_key), 'HTTP') === 0)) {
                    $detected_type = 'match_image_label';
                } elseif (is_array($decoded) && !isset($decoded[0]) && !is_numeric($first_key)) {
                    $detected_type = 'match_pair';
                }
            }
        }
        
        $formatted = array(
            'id' => $question->id,
            'title' => $question->title,
            'type' => $detected_type ?: $question->type,
            'options' => ($detected_type ?: $question->type) === 'multiple_choice' ? json_decode($question->options, true) : null,
            'correct_answer' => $question->correct_answer,
            'explanation' => $question->explanation
        );
        
        // Handle PRO question types
        if (($detected_type ?: $question->type) === 'fill_blanks') {
            // For fill_blanks, options field contains the question text with blanks
            $formatted['blanks_text'] = $question->options ? $question->options : '';
        }
        
        // Allow PRO addon to modify formatted question
        $formatted = apply_filters('livq_format_question_for_frontend', $formatted, $question);
        
        return $formatted;
    }
}
