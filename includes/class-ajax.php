<?php
/**
 * AJAX class
 */

if (!defined('ABSPATH')) {
    exit;
}

class LIVQ_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_livq_submit_quiz', array($this, 'submit_quiz'));
        add_action('wp_ajax_nopriv_livq_submit_quiz', array($this, 'submit_quiz'));
    }
    
    /**
     * Handle quiz submission
     */
    public function submit_quiz() {
        check_ajax_referer('livq_nonce', 'nonce');
        
        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in loop below
        $raw_answers = isset($_POST['answers']) ? wp_unslash($_POST['answers']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // Sanitize answers array
        $answers = array();
        if (is_array($raw_answers)) {
            foreach ($raw_answers as $key => $value) {
                $sanitized_key = sanitize_text_field($key);
                if (is_array($value)) {
                    $answers[$sanitized_key] = array_map('sanitize_text_field', $value);
                } else {
                    $answers[$sanitized_key] = sanitize_text_field($value);
                }
            }
        }
        $user_id = get_current_user_id();
        $user_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        
        if ($quiz_id <= 0) {
            wp_send_json_error(__('Invalid quiz ID.', 'lucrative-interactive-videoquiz'));
        }
        
        // Sanitize answers payload: ensure array of questionId => answer
        $sanitized_answers = array();
        if (is_array($answers)) {
            foreach ($answers as $qid => $value) {
                $qid = intval($qid);
                if ($qid <= 0) {
                    continue;
                }
                if (is_array($value) && isset($value['answer'])) {
                    $value = $value['answer'];
                }
                $sanitized_answers[$qid] = sanitize_text_field($value);
            }
        }
        
        // Get quiz data
        $quiz = LIVQ_Video_Manager::get_video_quiz($quiz_id);
        if (!$quiz) {
            wp_send_json_error('Quiz not found');
        }
        
        // Get questions
        $time_slots = json_decode($quiz->time_slots, true);
        $questions = LIVQ_Video_Manager::get_questions_for_time_slots($time_slots);
        
        // Calculate score
        $score = 0;
        $total_questions = 0;
        $question_results = array();
        
        foreach ($time_slots as $slot) {
            if (isset($slot['questions']) && is_array($slot['questions'])) {
                foreach ($slot['questions'] as $question_id) {
                    $question = null;
                    foreach ($questions as $q) {
                        if ($q->id == $question_id) {
                            $question = $q;
                            break;
                        }
                    }
                    
                    if ($question) {
                        $total_questions++;
                        $user_answer = isset($sanitized_answers[$question_id]) ? $sanitized_answers[$question_id] : null;
                        $is_correct = $this->check_answer($question, $user_answer);
                        
                        if ($is_correct) {
                            $score++;
                        }
                        
                        $question_results[$question_id] = array(
                            'user_answer' => $user_answer,
                            'correct_answer' => $question->correct_answer,
                            'is_correct' => $is_correct,
                            'explanation' => $question->explanation
                        );
                    }
                }
            }
        }
        
        // Save results
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'livq_quiz_results',
            array(
                'quiz_id' => $quiz_id,
                'user_id' => $user_id ?: null,
                'user_ip' => $user_ip,
                'answers' => wp_json_encode($question_results),
                'score' => $score,
                'total_questions' => $total_questions
            ),
            array('%d', '%d', '%s', '%s', '%d', '%d')
        );
        
        // Allow PRO addon to process gamification, analytics, email notifications, etc.
        do_action('livq_after_score_calculation', $quiz_id, $score, $total_questions, $question_results);
        
        wp_send_json_success(array(
            'score' => $score,
            'total_questions' => $total_questions,
            'percentage' => $total_questions > 0 ? round(($score / $total_questions) * 100) : 0,
            'results' => $question_results
        ));
    }
    
    /**
     * Check if answer is correct
     */
    private function check_answer($question, $user_answer) {
        if ($user_answer === null) {
            return false;
        }
        
        // Allow PRO addon to handle custom question types
        $custom_result = apply_filters('livq_check_custom_answer', null, $question, $user_answer);
        if ($custom_result !== null) {
            return $custom_result;
        }
        
        // Default question types
        if ($question->type === 'true_false') {
            return $user_answer === $question->correct_answer;
        } elseif ($question->type === 'multiple_choice') {
            return $user_answer == $question->correct_answer;
        }
        
        return false;
    }
}
