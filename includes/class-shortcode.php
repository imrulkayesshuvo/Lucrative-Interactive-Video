<?php
/**
 * Shortcode class
 */

if (!defined('ABSPATH')) {
    exit;
}

class LIVQ_Shortcode {
    
    public function __construct() {
        add_shortcode('livq_quiz', array($this, 'render_quiz_shortcode'));
    }
    
    /**
     * Render the quiz shortcode
     */
    public function render_quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0
        ), $atts);
        
        $quiz_id = intval($atts['id']);
        if (!$quiz_id) {
            return '<p>Invalid quiz ID.</p>';
        }
        
        $quiz = LIVQ_Video_Manager::get_video_quiz($quiz_id);
        if (!$quiz) {
            return '<p>Quiz not found.</p>';
        }
        
        $formatted_quiz = LIVQ_Video_Manager::format_video_quiz_for_frontend($quiz);
        
        $questions = LIVQ_Video_Manager::get_questions_for_time_slots($formatted_quiz['time_slots']);
        
        $formatted_questions = array();
        
        foreach ($questions as $question) {
            $formatted_questions[] = LIVQ_Question_Manager::format_question_for_frontend($question);
        }
        
        $settings = get_option('livq_settings', array());
        
        // Get video player dimensions from settings
        $video_width = isset($settings['video_width']) ? $settings['video_width'] : '100%';
        $video_height = isset($settings['video_height']) ? $settings['video_height'] : '400px';
        $video_responsive = isset($settings['video_responsive']) ? $settings['video_responsive'] : '1';
        $video_aspect_ratio = isset($settings['video_aspect_ratio']) ? $settings['video_aspect_ratio'] : '16:9';
        
        // Get quiz container dimensions from settings
        $container_width = isset($settings['container_width']) ? $settings['container_width'] : '100%';
        $container_height = isset($settings['container_height']) ? $settings['container_height'] : 'auto';
        $container_max_width = isset($settings['container_max_width']) ? $settings['container_max_width'] : '1200px';
        $container_alignment = isset($settings['container_alignment']) ? $settings['container_alignment'] : 'center';
        
        // Get quiz theme from settings
        $quiz_theme = isset($settings['quiz_theme']) ? $settings['quiz_theme'] : 'default';
        
        ob_start();
        ?>
        <?php
        // Calculate container styles
        $container_style = '';
        
        // Width
        if (strpos($container_width, '%') !== false) {
            $container_style .= 'width: ' . $container_width . ';';
        } elseif (is_numeric($container_width)) {
            $container_style .= 'width: ' . $container_width . 'px;';
        } else {
            $container_style .= 'width: ' . $container_width . ';';
        }
        
        // Height
        if ($container_height !== 'auto') {
            if (strpos($container_height, '%') !== false || strpos($container_height, 'vh') !== false) {
                $container_style .= 'height: ' . $container_height . ';';
            } elseif (is_numeric($container_height)) {
                $container_style .= 'height: ' . $container_height . 'px;';
            } else {
                $container_style .= 'height: ' . $container_height . ';';
            }
        }
        
        // Max width
        if (strpos($container_max_width, '%') !== false) {
            $container_style .= 'max-width: ' . $container_max_width . ';';
        } elseif (is_numeric($container_max_width)) {
            $container_style .= 'max-width: ' . $container_max_width . 'px;';
        } else {
            $container_style .= 'max-width: ' . $container_max_width . ';';
        }
        
        // Alignment
        $alignment_class = 'livq-align-' . $container_alignment;
        
        // Theme class
        $theme_class = 'livq-theme-' . $quiz_theme;
        ?>
        
        <div class="livq-quiz-container <?php echo esc_attr($alignment_class); ?> <?php echo esc_attr($theme_class); ?>" 
             style="<?php echo esc_attr($container_style); ?>"
             data-quiz-id="<?php echo esc_attr($quiz_id); ?>"
             data-video-type="<?php echo esc_attr($formatted_quiz['video_type']); ?>"
             data-video-source="<?php echo esc_url($formatted_quiz['video_url']); ?>"
             data-questions='<?php echo esc_attr(wp_json_encode($formatted_questions)); ?>'
             data-time-slots='<?php echo esc_attr(wp_json_encode($formatted_quiz['time_slots'])); ?>'
             data-show-correct="<?php echo esc_attr($settings['show_correct_answers'] ? '1' : '0'); ?>"
             data-allow-skip="<?php echo esc_attr($settings['allow_skipping'] ? '1' : '0'); ?>"
             data-end-message="<?php echo esc_attr($settings['completion_message'] ?? 'Congratulations! You have completed the video quiz.'); ?>">
            
            <?php $this->render_video_player($formatted_quiz, $video_width, $video_height, $video_responsive, $video_aspect_ratio); ?>
            
            <div class="livq-quiz-overlay" style="display: none;">
                <div class="livq-quiz-modal">
                    <div class="livq-quiz-header">
                        <h3>Quiz Question</h3>
                        <button class="livq-close-quiz" type="button">&times;</button>
                    </div>
                    <div class="livq-quiz-content">
                        <div class="livq-question-container">
                            <!-- Questions will be loaded here dynamically -->
                        </div>
                    </div>
                    <div class="livq-quiz-footer">
                        <button class="livq-skip-question" style="<?php echo $settings['allow_skipping'] ? '' : 'display: none;'; ?>">Skip</button>
                        <button class="livq-submit-answer">Submit Answer</button>
                    </div>
                </div>
            </div>
            
            <div class="livq-results-overlay" style="display: none;">
                <div class="livq-results-modal">
                    <div class="livq-results-header">
                        <h3>Quiz Results</h3>
                    </div>
                    <div class="livq-results-content">
                        <div class="livq-score-display">
                            <span class="livq-score">0</span>/<span class="livq-total">0</span>
                        </div>
                        <div class="livq-completion-message">
                            <?php echo esc_html($settings['completion_message'] ?? 'Congratulations! You have completed the video quiz.'); ?>
                        </div>
                    </div>
                    <div class="livq-results-footer">
                        <button class="livq-restart-quiz">Restart Quiz</button>
                        <button class="livq-close-results">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render video player based on type
     */
    private function render_video_player($quiz, $video_width = '100%', $video_height = '400px', $video_responsive = '1', $video_aspect_ratio = '16:9') {
        // Always use custom video player for better control
        $this->render_custom_video_player($quiz, $video_width, $video_height, $video_responsive, $video_aspect_ratio);
    }
    
    /**
     * Render YouTube player
     */
    private function render_youtube_player($quiz) {
        $video_id = $quiz['video_id'];
        ?>
        <div class="livq-youtube-player" data-video-id="<?php echo esc_attr($video_id); ?>">
            <iframe 
                id="livq-youtube-<?php echo esc_attr($quiz['id']); ?>"
                width="100%" 
                height="400" 
                src="https://www.youtube.com/embed/<?php echo esc_attr($video_id); ?>?enablejsapi=1&origin=<?php echo esc_url(home_url()); ?>"
                frameborder="0" 
                allowfullscreen>
            </iframe>
        </div>
        <?php
    }
    
    /**
     * Render Vimeo player
     */
    private function render_vimeo_player($quiz) {
        $video_id = $quiz['video_id'];
        ?>
        <div class="livq-vimeo-player" data-video-id="<?php echo esc_attr($video_id); ?>">
            <iframe 
                id="livq-vimeo-<?php echo esc_attr($quiz['id']); ?>"
                width="100%" 
                height="400" 
                src="https://player.vimeo.com/video/<?php echo esc_attr($video_id); ?>?api=1"
                frameborder="0" 
                allowfullscreen>
            </iframe>
        </div>
        <?php
    }
    
    /**
     * Render MP4 player
     */
    private function render_mp4_player($quiz) {
        ?>
        <div class="livq-mp4-player">
            <video 
                id="livq-mp4-<?php echo esc_attr($quiz['id']); ?>"
                width="100%" 
                height="400" 
                controls
                preload="metadata">
                <source src="<?php echo esc_url($quiz['video_url']); ?>" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>
        <?php
    }
    
    /**
     * Render custom video player with built-in quiz functionality
     */
    private function render_custom_video_player($quiz, $video_width = '100%', $video_height = '400px', $video_responsive = '1', $video_aspect_ratio = '16:9') {
        $video_url = $quiz['video_url'];
        $video_type = $quiz['video_type'];
        $video_id = '';
        
        // Extract video ID for YouTube/Vimeo
        if ($video_type === 'youtube') {
            $video_id = $this->extract_youtube_id($video_url);
        } elseif ($video_type === 'vimeo') {
            $video_id = $this->extract_vimeo_id($video_url);
        }
        ?>
        <?php
        // Calculate video dimensions
        $width_style = '';
        $height_style = '';
        $responsive_class = $video_responsive === '1' ? 'livq-responsive' : '';
        
        if ($video_aspect_ratio !== 'custom') {
            // Use aspect ratio
            $aspect_ratios = array(
                '16:9' => '56.25%',
                '4:3' => '75%',
                '21:9' => '42.86%',
                '1:1' => '100%'
            );
            $padding_bottom = $aspect_ratios[$video_aspect_ratio] ?? '56.25%';
            $responsive_class .= ' livq-aspect-ratio';
            
            // Set width for aspect ratio containers
            if (strpos($video_width, '%') !== false) {
                $width_style = 'width: ' . $video_width . ';';
            } elseif (is_numeric($video_width)) {
                $width_style = 'width: ' . $video_width . 'px;';
            } else {
                $width_style = 'width: ' . $video_width . ';';
            }
        } else {
            // Use custom dimensions
            if (strpos($video_width, '%') !== false) {
                $width_style = 'width: ' . $video_width . ';';
            } elseif (is_numeric($video_width)) {
                $width_style = 'width: ' . $video_width . 'px;';
            } else {
                $width_style = 'width: ' . $video_width . ';';
            }
            
            if (strpos($video_height, '%') !== false) {
                $height_style = 'height: ' . $video_height . ';';
            } elseif (is_numeric($video_height)) {
                $height_style = 'height: ' . $video_height . 'px;';
            } else {
                $height_style = 'height: ' . $video_height . 'px;';
            }
        }
        ?>
        
        <div class="livq-video-wrapper <?php echo esc_attr($responsive_class); ?>" 
             style="<?php echo esc_attr($width_style . $height_style); ?>"
             <?php if ($video_aspect_ratio !== 'custom'): ?>
             data-aspect-ratio="<?php echo esc_attr($video_aspect_ratio); ?>"
             <?php endif; ?>>
            <?php if ($video_type === 'youtube'): ?>
                <div class="livq-video-player" 
                     data-plyr-provider="youtube" 
                     data-plyr-embed-id="<?php echo esc_attr($video_id); ?>">
                </div>
                <!-- Fallback for YouTube -->
                <iframe 
                    src="https://www.youtube.com/embed/<?php echo esc_attr($video_id); ?>?enablejsapi=1" 
                    width="100%" 
                    height="<?php echo esc_attr($video_height); ?>" 
                    frameborder="0" 
                    allowfullscreen
                    style="display: none;"
                    class="livq-fallback-video">
                </iframe>
            <?php elseif ($video_type === 'vimeo'): ?>
                <div class="livq-video-player" 
                     data-plyr-provider="vimeo" 
                     data-plyr-embed-id="<?php echo esc_attr($video_id); ?>">
                </div>
                <!-- Fallback for Vimeo -->
                <iframe 
                    src="https://player.vimeo.com/video/<?php echo esc_attr($video_id); ?>" 
                    width="100%" 
                    height="<?php echo esc_attr($video_height); ?>" 
                    frameborder="0" 
                    allowfullscreen
                    style="display: none;"
                    class="livq-fallback-video">
                </iframe>
            <?php else: ?>
                <video class="livq-video-player" playsinline controls>
                    <source src="<?php echo esc_url($video_url); ?>" type="video/mp4" />
                </video>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Extract YouTube video ID from URL
     */
    private function extract_youtube_id($url) {
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Extract Vimeo video ID from URL
     */
    private function extract_vimeo_id($url) {
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
