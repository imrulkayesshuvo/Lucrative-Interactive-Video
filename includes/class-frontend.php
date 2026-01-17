<?php
/**
 * Frontend class
 */

if (!defined('ABSPATH')) {
    exit;
}

class LIVQ_Frontend {
    
    public function __construct() {
        // Frontend functionality is handled by JavaScript
        // This class can be extended for additional frontend features

        // Auto-attach video quizzes to Tutor LMS lessons (no shortcode needed)
        add_filter('the_content', array($this, 'inject_quiz_into_tutor_lesson'), 20);

        // Tutor LMS specific hooks (in case templates bypass the_content)
        add_filter('tutor_lesson/single/content', array($this, 'inject_quiz_into_tutor_lesson'), 20);
        add_action('tutor/lesson/single/after/content', array($this, 'render_quiz_after_content'), 20);
    }

    /**
     * If the current post is a Tutor LMS lesson and there is a Video Quiz
     * mapped to this lesson (video_type = tutor_lms and video_url = lesson ID),
     * automatically inject the quiz output without requiring a shortcode.
     */
    public function inject_quiz_into_tutor_lesson($content) {
        // Avoid running in admin or on non-singular views
        if (is_admin() || !is_singular()) {
            return $content;
        }

        global $post, $wpdb;

        if (!$post) {
            return $content;
        }

        // Detect Tutor LMS lesson post type
        $lesson_post_types = array('lesson', 'tutor_lessons');
        if (function_exists('tutor')) {
            $tutor_instance = tutor();
            if (property_exists($tutor_instance, 'lesson_post_type') && !empty($tutor_instance->lesson_post_type)) {
                $lesson_post_types[] = $tutor_instance->lesson_post_type;
            }
        }

        if (!in_array($post->post_type, array_unique($lesson_post_types), true)) {
            return $content;
        }

        // Look up a video quiz linked to this lesson.
        // Also consider rows where video_type is empty but video_url matches (older saves).
        $quiz_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, video_type FROM {$wpdb->prefix}livq_video_quizzes 
                 WHERE video_url = %d 
                 AND (video_type = %s OR video_type = '' OR video_type IS NULL)
                 LIMIT 1",
                $post->ID,
                'tutor_lms'
            )
        );

        if (!$quiz_row || empty($quiz_row->id)) {
            return $content; // No quiz mapped to this lesson
        }

        // If the quiz had empty video_type, set it to tutor_lms for future saves
        if (empty($quiz_row->video_type)) {
            $wpdb->update(
                $wpdb->prefix . 'livq_video_quizzes',
                array('video_type' => 'tutor_lms'),
                array('id' => $quiz_row->id)
            );
        }

        // Render the quiz via shortcode so all scripts/styles and data attributes load
        $quiz_shortcode = '[livq_quiz id="' . intval($quiz_row->id) . '"]';

        // Append the quiz to the content (after lesson content)
        return $content . do_shortcode($quiz_shortcode);
    }

    /**
     * Render quiz after content (action hook variant for Tutor LMS templates).
     */
    public function render_quiz_after_content() {
        // Reuse inject logic and output it
        $output = $this->inject_quiz_into_tutor_lesson('');
        if (!empty($output)) {
            echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
