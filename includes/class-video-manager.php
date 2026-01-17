<?php
/**
 * Video Manager class
 */

if (!defined('ABSPATH')) {
    exit;
}

class LIVQ_Video_Manager {
    
    public function __construct() {
        // Video management methods are handled in the dashboard class
        // This class can be extended for additional video-related functionality
    }
    
    /**
     * Get all video quizzes
     */
    public static function get_video_quizzes() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}livq_video_quizzes ORDER BY created_at DESC");
    }
    
    /**
     * Get video quiz by ID
     */
    public static function get_video_quiz($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}livq_video_quizzes WHERE id = %d", $id));
    }
    
    /**
     * Get video quiz by shortcode
     */
    public static function get_video_quiz_by_shortcode($shortcode) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}livq_video_quizzes WHERE shortcode = %s", $shortcode));
    }
    
    /**
     * Validate video URL
     */
    public static function validate_video_url($url, $type) {
        switch ($type) {
            case 'youtube':
                return preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)/', $url);
            case 'vimeo':
                return preg_match('/^(https?:\/\/)?(www\.)?vimeo\.com\//', $url);
            case 'mp4':
                return preg_match('/\.mp4$/i', $url);
            default:
                return false;
        }
    }
    
    /**
     * Extract video ID from URL
     */
    public static function extract_video_id($url, $type) {
        switch ($type) {
            case 'youtube':
                if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $url, $matches)) {
                    return $matches[1];
                }
                break;
            case 'vimeo':
                if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
                    return $matches[1];
                }
                break;
        }
        return null;
    }
    
    /**
     * Format video quiz for frontend display
     */
    public static function format_video_quiz_for_frontend($quiz) {
        $time_slots = json_decode($quiz->time_slots ?? '[]', true);
        $settings = json_decode($quiz->settings ?? '{}', true);
        
        $video_url = $quiz->video_url;
        $video_type = $quiz->video_type;
        // Fallback: if video_type empty but video_url looks like a Tutor lesson ID, assume tutor_lms
        if (empty($video_type) && is_numeric($video_url)) {
            $video_type = 'tutor_lms';
        }
        $video_id = null;
        
        // Handle Tutor LMS videos
        if ($video_type === 'tutor_lms' && function_exists('tutor_utils')) {
            $lesson_id = intval($video_url);
            if ($lesson_id > 0) {
                $video = tutor_utils()->get_video($lesson_id);
                if ($video && !empty($video)) {
                    $video_source = isset($video['source']) ? $video['source'] : '';
                    
                    // Extract video URL based on source
                    if ($video_source === 'youtube') {
                        $video_url = isset($video['source_youtube']) ? $video['source_youtube'] : '';
                        $video_type = 'youtube';
                    } elseif ($video_source === 'vimeo') {
                        $video_url = isset($video['source_vimeo']) ? $video['source_vimeo'] : '';
                        $video_type = 'vimeo';
                    } elseif ($video_source === 'html5') {
                        $video_url = isset($video['source_video_id']) ? wp_get_attachment_url($video['source_video_id']) : '';
                        $video_type = 'mp4';
                    } elseif ($video_source === 'external_link') {
                        $video_url = isset($video['source_external_url']) ? $video['source_external_url'] : '';
                        $video_type = 'mp4'; // Treat external links as MP4
                    }
                    
                    // Extract video ID if available
                    if ($video_url) {
                        $video_id = self::extract_video_id($video_url, $video_type);
                    }
                }
            }
        } else {
            $video_id = self::extract_video_id($video_url, $video_type);
        }
        
        $formatted = array(
            'id' => $quiz->id,
            'title' => $quiz->title,
            'video_url' => $video_url,
            'video_type' => $video_type,
            'video_id' => $video_id,
            'time_slots' => $time_slots ?: array(),
            'settings' => $settings ?: array()
        );
        
        return $formatted;
    }
    
    /**
     * Get questions for time slots
     */
    public static function get_questions_for_time_slots($time_slots) {
        $all_question_ids = array();
        
        foreach ($time_slots as $slot) {
            if (isset($slot['questions']) && is_array($slot['questions'])) {
                // Normalize question IDs to integers
                foreach ($slot['questions'] as $qid) {
                    $qid_int = intval($qid);
                    if ($qid_int > 0) {
                        $all_question_ids[] = $qid_int;
                    }
                }
            }
        }
        
        if (empty($all_question_ids)) {
            return array();
        }
        
        // Remove duplicates and get questions
        $unique_ids = array_unique($all_question_ids);
        return LIVQ_Question_Manager::get_questions_by_ids($unique_ids);
    }
}
