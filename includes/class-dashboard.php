<?php
/**
 * Custom Dashboard class
 */

if (!defined('ABSPATH')) {
    exit;
}

class LIVQ_Dashboard {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'check_database_update'));
        add_action('wp_ajax_livq_save_question', array($this, 'save_question'));
        add_action('wp_ajax_livq_delete_question', array($this, 'delete_question'));
        add_action('wp_ajax_livq_save_video_quiz', array($this, 'save_video_quiz'));
        add_action('wp_ajax_livq_delete_video_quiz', array($this, 'delete_video_quiz'));
        add_action('wp_ajax_livq_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_livq_get_questions', array($this, 'get_questions'));
        add_action('wp_ajax_livq_get_video_duration', array($this, 'get_video_duration'));
        add_action('wp_ajax_livq_get_tutor_lms_video', array($this, 'get_tutor_lms_video'));
    }
    
    /**
     * Check and update database schema if needed
     */
    public function check_database_update() {
        // Only run on plugin admin pages
        if (!isset($_GET['page']) || $_GET['page'] !== 'livq-dashboard') {
            return;
        }
        
        // Run update only once per day to avoid performance issues
        // But always run if we're on the questions tab (to fix existing questions)
        $force_update = isset($_GET['tab']) && $_GET['tab'] === 'questions';
        $last_update = get_option('livq_db_last_update', 0);
        
        if (!$force_update && (time() - $last_update < 86400)) { // 24 hours
            return;
        }
        
        if (class_exists('LIVQ_Database')) {
            $database = new LIVQ_Database();
            $database->update_tables();
            update_option('livq_db_last_update', time());
        }
    }
    
    /**
     * Documentation submenu page handler
     */
    public function documentation_page() {
        // Route to the same renderer as documentation tab to keep content in one place
        echo '<div class="wrap">';
        $this->documentation_tab();
        echo '</div>';
    }

    /**
     * Documentation tab
     */
    private function documentation_tab() {
        ?>
        <div class="livq-tab-content livq-docs">
            <h2>Documentation</h2>
            <p>Step-by-step guides with visuals to help you use the plugin.</p>

            <div class="livq-docs-section">
                <h3>1) Create Questions</h3>
                <ol>
                    <li>Go to <strong>Video Quiz → Questions</strong> and click <strong>Add New Question</strong>.</li>
                    <li>Choose <strong>True/False</strong> or <strong>Multiple Choice</strong>.</li>
                    <li>Fill the title, options (up to 4 in free), and select the correct answer.</li>
                    <li>Save. You can create up to 10 questions in the free version.</li>
                </ol>
            </div>

            <div class="livq-docs-section">
                <h3>2) Create a Video Quiz</h3>
                <ol>
                    <li>Go to <strong>Video Quiz → Video Quizzes</strong> and click <strong>Create Video Quiz</strong>.</li>
                    <li>Select video type (YouTube, Vimeo, MP4) and paste the video URL.</li>
                    <li>Add <strong>Time Slots</strong> and assign questions to each time.</li>
                    <li>Save. Use the shortcode shown to embed the quiz.</li>
                </ol>
            </div>

            <div class="livq-docs-section">
                <h3>3) Settings</h3>
                <ol>
                    <li>Go to <strong>Video Quiz → Settings</strong>.</li>
                    <li>Configure answer visibility, skipping, completion message, and theme.</li>
                    <li>Adjust player size/aspect and container alignment.</li>
                </ol>
            </div>

            <div class="livq-docs-section">
                <h3>4) Frontend Usage</h3>
                <ol>
                    <li>Add the shortcode <code>[livq_quiz id="123"]</code> to any page/post.</li>
                    <li>Video will pause at configured times and show questions in an overlay.</li>
                    <li>Results are shown at the end and attempts are recorded in Reports.</li>
                </ol>
            </div>

            <style>
            .livq-docs .livq-docs-section { background:#fff; border:1px solid #e5e5e5; border-radius:6px; padding:16px; margin:16px 0; }
            .livq-docs .livq-docs-visual { margin-top:12px; }
            .livq-docs .livq-docs-placeholder { display:flex; align-items:center; justify-content:center; height:180px; background:#f6f7f7; border:1px dashed #c3c4c7; color:#777; border-radius:4px; }
            .livq-docs code { background:#f0f0f1; padding:2px 6px; border-radius:3px; }
            </style>
        </div>
        <?php
    }

    public function add_admin_menu() {
        add_menu_page(
            'Video Quiz Dashboard',
            'Video Quiz',
            'manage_options',
            'livq-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-video-alt3',
            30
        );
        
        // Add Pro Features submenu
        add_submenu_page(
            'livq-dashboard',
            'Pro Features',
            'Pro Features',
            'manage_options',
            'livq-pro-features',
            array($this, 'pro_features_page')
        );

        // Add Documentation submenu
        add_submenu_page(
            'livq-dashboard',
            'Documentation',
            'Documentation',
            'manage_options',
            'livq-documentation',
            array($this, 'documentation_page')
        );
    }
    
    public function dashboard_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'lucrative-interactive-video'));
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET request for tab navigation, capability check above
        $current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'dashboard';
        ?>
        <div class="wrap">
            <div class="livq-dashboard">
            <div class="livq-header">
                <h1>🎓 Lucrative Interactive VideoQuiz</h1>
                <p>Make your videos smarter — engage learners with in-video questions</p>
            </div>
            
            <div class="livq-dashboard-container">
                <div class="livq-sidebar">
                    <nav class="livq-nav">
                        <a href="?page=livq-dashboard&tab=dashboard" class="livq-nav-item <?php echo $current_tab === 'dashboard' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-dashboard"></span>
                            Dashboard
                        </a>
                        <a href="?page=livq-dashboard&tab=questions" class="livq-nav-item <?php echo $current_tab === 'questions' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-editor-help"></span>
                            Questions
                        </a>
                        <a href="?page=livq-dashboard&tab=videos" class="livq-nav-item <?php echo $current_tab === 'videos' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-video-alt3"></span>
                            Video Quizzes
                        </a>
                        <a href="?page=livq-dashboard&tab=settings" class="livq-nav-item <?php echo $current_tab === 'settings' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-admin-settings"></span>
                            Settings
                        </a>
                        <a href="?page=livq-dashboard&tab=reports" class="livq-nav-item <?php echo $current_tab === 'reports' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-chart-bar"></span>
                            Reports
                        </a>
                        <a href="?page=livq-dashboard&tab=documentation" class="livq-nav-item <?php echo $current_tab === 'documentation' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-media-document"></span>
                            Documentation
                        </a>
                    </nav>
                </div>
                
                <div class="livq-content">
                    <?php
                    switch ($current_tab) {
                        case 'dashboard':
                            $this->dashboard_tab();
                            break;
                        case 'questions':
                            $this->questions_tab();
                            break;
                        case 'videos':
                            $this->videos_tab();
                            break;
                        case 'settings':
                            $this->settings_tab();
                            break;
                        case 'reports':
                            $this->reports_tab();
                            break;
                        case 'documentation':
                            $this->documentation_tab();
                            break;
                        default:
                            $this->dashboard_tab();
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    }
    
    private function dashboard_tab() {
        global $wpdb;
        
        $questions_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}livq_questions");
        $quizzes_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}livq_video_quizzes");
        // Hardened results count: ensure table exists
        $results_table = $wpdb->prefix . 'livq_quiz_results';
        $have_results_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $results_table)) === $results_table;
        if (!$have_results_table && class_exists('LIVQ_Database')) {
            $db = new LIVQ_Database();
            $db->create_tables();
            $have_results_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $results_table)) === $results_table;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be prepared, safely interpolated
        $results_count = $have_results_table ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$results_table}") : 0;
        
        // Free plugin limits (can be overridden by PRO addon)
        $max_questions = apply_filters('livq_max_questions_limit', 10);
        $max_videos = apply_filters('livq_max_videos_limit', 5);
        
        // Check if PRO version is active
        $is_pro_active = class_exists('LIVQ_Limits_Remover') && method_exists('LIVQ_Limits_Remover', 'is_unlimited') && LIVQ_Limits_Remover::is_unlimited();
        $is_questions_unlimited = ($max_questions === PHP_INT_MAX || $is_pro_active);
        $is_videos_unlimited = ($max_videos === PHP_INT_MAX || $is_pro_active);
        ?>
        <div class="livq-tab-content">
            <h2>Dashboard Overview</h2>
            
            <div class="livq-stats-grid">
                <div class="livq-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-editor-help"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php 
                            if ($is_questions_unlimited) {
                                echo esc_html($questions_count);
                                echo ' <span class="livq-unlimited-badge"><span class="livq-infinity-icon">∞</span> ' . esc_html__('Unlimited', 'lucrative-interactive-video') . '</span>';
                            } else {
                                echo esc_html($questions_count) . ' / ' . esc_html($max_questions);
                            }
                        ?></h3>
                        <p><?php echo $is_questions_unlimited ? esc_html__('Questions', 'lucrative-interactive-video') : esc_html__('Questions (Free Limit)', 'lucrative-interactive-video'); ?></p>
                        <?php if (!$is_questions_unlimited && $questions_count >= $max_questions): ?>
                            <div class="livq-limit-reached">⚠️ Limit Reached</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="livq-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-video-alt3"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php 
                            if ($is_videos_unlimited) {
                                echo esc_html($quizzes_count);
                                echo ' <span class="livq-unlimited-badge"><span class="livq-infinity-icon">∞</span> ' . esc_html__('Unlimited', 'lucrative-interactive-video') . '</span>';
                            } else {
                                echo esc_html($quizzes_count) . ' / ' . esc_html($max_videos);
                            }
                        ?></h3>
                        <p><?php echo $is_videos_unlimited ? esc_html__('Video Quizzes', 'lucrative-interactive-video') : esc_html__('Video Quizzes (Free Limit)', 'lucrative-interactive-video'); ?></p>
                        <?php if (!$is_videos_unlimited && $quizzes_count >= $max_videos): ?>
                            <div class="livq-limit-reached">⚠️ Limit Reached</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="livq-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-bar"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo esc_html($results_count); ?></h3>
                        <p>Quiz Attempts</p>
                    </div>
                </div>
            </div>
            
            <?php
            // Allow PRO addon to display its status
            do_action('livq_after_dashboard_stats');
            ?>
            
            <?php if (!apply_filters('livq_hide_upgrade_notices', false) && ($questions_count >= $max_questions || $quizzes_count >= $max_videos)): ?>
            <div class="livq-upgrade-notice">
                <h3>🚀 Upgrade to Pro for Unlimited Features!</h3>
                <p>You've reached the free plugin limits. Upgrade to Pro for:</p>
                <ul>
                    <li>✅ Unlimited Questions</li>
                    <li>✅ Unlimited Video Quizzes</li>
                    <li>✅ Advanced Question Types</li>
                    <li>✅ Detailed Reports</li>
                    <li>✅ LMS Integration</li>
                </ul>
                <a href="#" class="button button-primary">Upgrade to Pro</a>
            </div>
            <?php endif; ?>
            
            <div class="livq-quick-actions">
                <h3>Quick Actions</h3>
                <div class="action-buttons">
                    <a href="?page=livq-dashboard&tab=questions&action=add" class="button button-primary">
                        <span class="dashicons dashicons-plus"></span>
                        Add New Question
                    </a>
                    <a href="?page=livq-dashboard&tab=videos&action=add" class="button button-primary">
                        <span class="dashicons dashicons-video-alt3"></span>
                        Create Video Quiz
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function questions_tab() {
        global $wpdb;
        
        // Verify nonce for action
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET request for action, capability check below
        if (isset($_GET['action'])) {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to perform this action.', 'lucrative-interactive-video'));
            }
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET request for action, capability check above
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        
        if ($action === 'add' || $action === 'edit') {
            $this->question_form($action);
        } else {
            $this->questions_list();
        }
    }
    
    private function questions_list() {
        global $wpdb;
        
        $questions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}livq_questions ORDER BY created_at DESC");
        ?>
        <div class="livq-tab-content">
            <div class="livq-header-actions">
                <h2>Questions Management</h2>
                <a href="?page=livq-dashboard&tab=questions&action=add" class="button button-primary">
                    <span class="dashicons dashicons-plus"></span>
                    Add New Question
                </a>
            </div>
            
            <div class="livq-table-container">
                <table class="livq-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Correct Answer</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $question): ?>
                        <tr>
                            <td><?php echo esc_html($question->id); ?></td>
                            <td><?php echo esc_html($question->title); ?></td>
                            <td>
                                <?php 
                                // Get question type label from filter (allows PRO addon to add custom types)
                                $question_types = apply_filters('livq_question_types', array(
                                    'true_false' => __('True/False', 'lucrative-interactive-video'),
                                    'multiple_choice' => __('Multiple Choice', 'lucrative-interactive-video')
                                ));
                                
                                // If type is empty, try to detect from correct_answer structure
                                if (empty($question->type)) {
                                    $decoded = json_decode($question->correct_answer, true);
                                    if (is_array($decoded) && !empty($decoded)) {
                                        // Check if it's an associative array with URLs as keys (match_image_label)
                                        $first_key = key($decoded);
                                        if (is_string($first_key) && (strpos($first_key, 'http://') === 0 || strpos($first_key, 'https://') === 0 || strpos($first_key, '/') === 0)) {
                                            $question->type = 'match_image_label';
                                        } elseif (is_array($decoded) && !isset($decoded[0])) {
                                            $question->type = 'match_pair';
                                        }
                                    }
                                }
                                
                                $type_label = isset($question_types[$question->type]) ? $question_types[$question->type] : ucfirst(str_replace('_', ' ', $question->type));
                                if (empty($type_label)) {
                                    $type_label = __('Unknown', 'lucrative-interactive-video');
                                }
                                ?>
                                <span class="livq-badge livq-badge-<?php echo esc_attr($question->type ?: 'unknown'); ?>">
                                    <?php echo esc_html($type_label); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $correct_answer = $question->correct_answer;
                                
                                // If type is empty, try to detect from correct_answer structure
                                if (empty($question->type)) {
                                    $decoded = json_decode($correct_answer, true);
                                    if (is_array($decoded) && !empty($decoded)) {
                                        // Check if it's an associative array with URLs as keys (match_image_label)
                                        $first_key = key($decoded);
                                        if (is_string($first_key) && (strpos($first_key, 'http://') === 0 || strpos($first_key, 'https://') === 0 || strpos($first_key, '/') === 0 || strpos($first_key, 'HTTP') === 0)) {
                                            $question->type = 'match_image_label';
                                        } elseif (is_array($decoded) && !isset($decoded[0]) && !is_numeric($first_key)) {
                                            $question->type = 'match_pair';
                                        }
                                    }
                                }
                                
                                if ($question->type === 'true_false') {
                                    $answer_text = $correct_answer === 'true' ? 'True' : 'False';
                                    $answer_class = $correct_answer === 'true' ? 'livq-answer-true' : 'livq-answer-false';
                                    echo '<span class="livq-correct-answer ' . esc_attr($answer_class) . '">' . esc_html($answer_text) . '</span>';
                                } else if ($question->type === 'short_answer') {
                                    echo '<span class="livq-correct-answer livq-answer-choice">' . esc_html($correct_answer) . '</span>';
                                } else if ($question->type === 'multiple_choice') {
                                    $options = json_decode($question->options, true);
                                    if ($options && isset($options[$correct_answer])) {
                                        $answer_text = esc_html($options[$correct_answer]);
                                        $answer_class = 'livq-answer-choice';
                                    } else {
                                        $answer_text = 'Option ' . ($correct_answer + 1);
                                        $answer_class = 'livq-answer-choice';
                                    }
                                    echo '<span class="livq-correct-answer ' . esc_attr($answer_class) . '">' . esc_html($answer_text) . '</span>';
                                } else if ($question->type === 'drag_drop') {
                                    $items = json_decode($correct_answer, true);
                                    if (is_array($items) && !empty($items)) {
                                        $item_count = count($items);
                                        echo '<div class="livq-drag-drop-preview" style="display: flex; flex-direction: column; gap: 2px;">';
                                        foreach (array_slice($items, 0, 3) as $index => $item) {
                                            $label = is_array($item) ? ($item['label'] ?? 'Item ' . ($index+1)) : $item;
                                            echo '<div style="font-size: 11px; background: #fdf2f8; padding: 2px 6px; border-radius: 4px; border: 1px solid #fbcfe8; color: #be185d;">';
                                            echo ($index + 1) . '. ' . esc_html($label);
                                            echo '</div>';
                                        }
                                        if (count($items) > 3) {
                                            echo '<div style="font-size: 10px; color: #666; padding-left: 6px;">+ ' . (count($items) - 3) . ' ' . esc_html__('more...', 'lucrative-interactive-video') . '</div>';
                                        }
                                        echo '</div>';
                                    } else {
                                        echo '<span class="livq-correct-answer livq-answer-default">' . esc_html__('No items', 'lucrative-interactive-video') . '</span>';
                                    }
                                } else if ($question->type === 'match_pair') {
                                    $pairs = json_decode($correct_answer, true);
                                    if (is_array($pairs) && !empty($pairs)) {
                                        echo '<div class="livq-match-pairs-preview" style="display: flex; flex-direction: column; gap: 2px;">';
                                        $index = 0;
                                        foreach ($pairs as $left => $right) {
                                            if ($index >= 3) break;
                                            echo '<div style="font-size: 12px; background: #f0f7ff; padding: 2px 6px; border-radius: 4px; border: 1px solid #d0e7ff; color: #0056b3;">';
                                            echo esc_html($left) . ' <span style="color: #666; font-size: 10px;">→</span> ' . esc_html($right);
                                            echo '</div>';
                                            $index++;
                                        }
                                        if (count($pairs) > 3) {
                                            echo '<div style="font-size: 10px; color: #666; padding-left: 6px;">+ ' . (count($pairs) - 3) . ' ' . esc_html__('more...', 'lucrative-interactive-video') . '</div>';
                                        }
                                        echo '</div>';
                                    } else {
                                        echo '<span class="livq-correct-answer livq-answer-default">' . esc_html__('No pairs', 'lucrative-interactive-video') . '</span>';
                                    }
                                } else if ($question->type === 'match_image_label') {
                                    $pairs = json_decode($correct_answer, true);
                                    if (is_array($pairs) && !empty($pairs)) {
                                        $pair_count = count($pairs);
                                        echo '<div class="livq-drag-drop-preview">';
                                        echo '<span class="livq-image-count-badge">' . esc_html($pair_count) . ' ' . _n('image', 'images', $pair_count, 'lucrative-interactive-video') . '</span>';
                                        echo '<div class="livq-image-thumbnails" style="display: flex; gap: 5px; margin-top: 5px; flex-wrap: wrap;">';
                                        $index = 0;
                                        foreach ($pairs as $image_url => $label) {
                                            if ($index >= 4) break;
                                            echo '<div style="text-align:center;">';
                                            echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($label) . '" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">';
                                            echo '<div style="font-size:10px; color:#666; margin-top:2px; max-width:40px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . esc_html(substr($label, 0, 8)) . '</div>';
                                            echo '</div>';
                                            $index++;
                                        }
                                        if (count($pairs) > 4) {
                                            echo '<span style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #f0f0f0; border-radius: 4px; font-size: 12px; color: #666;">+' . (count($pairs) - 4) . '</span>';
                                        }
                                        echo '</div>';
                                        echo '</div>';
                                    } else {
                                        echo '<span class="livq-correct-answer livq-answer-default">' . esc_html__('No images', 'lucrative-interactive-video') . '</span>';
                                    }
                                } else if ($question->type === 'fill_blanks') {
                                    $answers = json_decode($correct_answer, true);
                                    if (is_array($answers) && !empty($answers)) {
                                        echo '<div class="livq-blanks-preview" style="display: flex; flex-wrap: wrap; gap: 4px;">';
                                        foreach ($answers as $index => $ans) {
                                            echo '<span style="font-size: 11px; background: #ecfdf5; padding: 2px 6px; border-radius: 4px; border: 1px solid #a7f3d0; color: #065f46; font-weight: 600;">' . esc_html($ans) . '</span>';
                                        }
                                        echo '</div>';
                                    } else {
                                        echo '<span class="livq-correct-answer livq-answer-default">' . esc_html(substr($correct_answer, 0, 50)) . (strlen($correct_answer) > 50 ? '...' : '') . '</span>';
                                    }
                                } else {
                                    // Fallback for unknown types - truncate long JSON
                                    $display_text = strlen($correct_answer) > 100 ? substr($correct_answer, 0, 100) . '...' : $correct_answer;
                                    echo '<span class="livq-correct-answer livq-answer-default" title="' . esc_attr($correct_answer) . '">' . esc_html($display_text) . '</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html(gmdate('M j, Y', strtotime($question->created_at))); ?></td>
                            <td>
                                <a href="?page=livq-dashboard&tab=questions&action=edit&id=<?php echo esc_attr($question->id); ?>" class="button button-small">
                                    Edit
                                </a>
                                <button class="button button-small button-link-delete livq-delete-question" data-id="<?php echo esc_attr($question->id); ?>">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    private function question_form($action) {
        // Verify nonce if editing
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET request for ID, capability check below
        if ($action === 'edit' && isset($_GET['id'])) {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to perform this action.', 'lucrative-interactive-video'));
            }
        }
        
        $question = null;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET request for ID, capability check above
        if ($action === 'edit' && isset($_GET['id'])) {
            global $wpdb;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET request for ID, capability check above
            $question_id = intval($_GET['id']);
            $question = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}livq_questions WHERE id = %d", $question_id));
        }

        $metadata = array();
        if ($question && !empty($question->metadata)) {
            $metadata = json_decode($question->metadata, true);
        }
        $branching = isset($metadata['branching']) ? $metadata['branching'] : array();
        ?>
        <div class="livq-tab-content">
            <div class="livq-header-actions">
                <h2><?php echo $action === 'add' ? 'Add New Question' : 'Edit Question'; ?></h2>
                <a href="?page=livq-dashboard&tab=questions" class="button">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    Back to Questions
                </a>
            </div>
            
            <form id="livq-question-form" class="livq-form">
                <div class="livq-form-group">
                    <label for="question_title">Question Title *</label>
                    <input type="text" id="question_title" name="title" value="<?php echo $question ? esc_attr($question->title) : ''; ?>" required>
                </div>
                
                <div class="livq-form-group">
                <label for="question_type">Question Type *</label>
                <select id="question_type" name="type" required>
                    <?php
                    // Allow PRO addon to add more question types
                    $question_types = apply_filters('livq_question_types', array(
                        'true_false' => __('True/False', 'lucrative-interactive-video'),
                        'multiple_choice' => __('Multiple Choice', 'lucrative-interactive-video'),
                        'short_answer' => __('Short Answer', 'lucrative-interactive-video')
                    ));
                    
                    foreach ($question_types as $type_value => $type_label):
                    ?>
                    <option value="<?php echo esc_attr($type_value); ?>" <?php echo ($question && $question->type === $type_value) ? 'selected' : ''; ?>>
                        <?php echo esc_html($type_label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php
            // Allow PRO addon to add custom question form fields
            do_action('livq_after_question_form', $action, $question);
            ?>
                
                <div class="livq-form-group" id="options-container" style="<?php echo (!$question || $question->type === 'true_false') ? 'display: none;' : ''; ?>">
                    <label>Options (for Multiple Choice) - Free Version: Max 4 Options</label>
                    <div id="options-list">
                        <?php if ($question && $question->type === 'multiple_choice'): ?>
                            <?php 
                            $options = json_decode($question->options, true);
                            if ($options): 
                                foreach ($options as $index => $option): 
                            ?>
                            <div class="option-item">
                                <input type="text" name="options[]" value="<?php echo esc_attr($option); ?>" placeholder="<?php echo esc_attr(sprintf('Option %d', $index + 1)); ?>">
                                <?php if (count($options) > 1): ?>
                                <button type="button" class="remove-option">Remove</button>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; endif; ?>
                        <?php else: ?>
                            <!-- Default 4 options for new multiple choice questions -->
                            <div class="option-item">
                                <input type="text" name="options[]" value="" placeholder="Option 1" required>
                                <button type="button" class="remove-option">Remove</button>
                            </div>
                            <div class="option-item">
                                <input type="text" name="options[]" value="" placeholder="Option 2" required>
                                <button type="button" class="remove-option">Remove</button>
                            </div>
                            <div class="option-item">
                                <input type="text" name="options[]" value="" placeholder="Option 3" required>
                                <button type="button" class="remove-option">Remove</button>
                            </div>
                            <div class="option-item">
                                <input type="text" name="options[]" value="" placeholder="Option 4" required>
                                <button type="button" class="remove-option">Remove</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="livq-pro-notice">
                        <p><strong>Free Version:</strong> Limited to 4 options. <a href="#" class="livq-upgrade-link">Upgrade to Pro</a> for unlimited options.</p>
                    </div>
                </div>
                
                <div class="livq-form-group">
                    <label for="correct_answer">Correct Answer *</label>
                    <div id="correct-answer-container">
                        <?php if (!$question || $question->type === 'true_false'): ?>
                        <select id="correct_answer" name="correct_answer" required>
                            <option value="true" <?php echo ($question && $question->correct_answer === 'true') ? 'selected' : ''; ?>>True</option>
                            <option value="false" <?php echo ($question && $question->correct_answer === 'false') ? 'selected' : ''; ?>>False</option>
                        </select>
                        <?php else: ?>
                        <select id="correct_answer" name="correct_answer" required>
                            <?php 
                            $options = json_decode($question->options, true);
                            if ($options): 
                                foreach ($options as $index => $option): 
                            ?>
                            <option value="<?php echo esc_attr($index); ?>" <?php echo ($question && $question->correct_answer == $index) ? 'selected' : ''; ?>>
                                <?php echo esc_html($option); ?>
                            </option>
                            <?php endforeach; endif; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="livq-form-group">
                    <label>Branching Logic (Jump to Time)</label>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #e9ecef;">
                        <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 10px;">
                            <div style="flex: 1;">
                                <label style="font-size: 13px; color: #155724;">If Answer is Correct, jump to (sec):</label>
                                <input type="number" name="metadata[branching][correct_jump]" value="<?php echo isset($branching['correct_jump']) ? esc_attr($branching['correct_jump']) : ''; ?>" placeholder="e.g. 120" min="0">
                            </div>
                            <div style="flex: 1;">
                                <label style="font-size: 13px; color: #721c24;">If Answer is Incorrect, jump to (sec):</label>
                                <input type="number" name="metadata[branching][incorrect_jump]" value="<?php echo isset($branching['incorrect_jump']) ? esc_attr($branching['incorrect_jump']) : ''; ?>" placeholder="e.g. 45" min="0">
                            </div>
                        </div>
                        <p class="description">Leave blank to continue playing normally. Destination time is in seconds.</p>
                    </div>
                </div>
                
                <div class="livq-form-group">
                    <label for="explanation">Explanation (Optional)</label>
                    <textarea id="explanation" name="explanation" rows="3"><?php echo $question ? esc_textarea($question->explanation) : ''; ?></textarea>
                </div>
                
                <div class="livq-form-actions">
                    <button type="submit" class="button button-primary">
                        <?php echo $action === 'add' ? 'Add Question' : 'Update Question'; ?>
                    </button>
                    <a href="?page=livq-dashboard&tab=questions" class="button">Cancel</a>
                </div>
                
                <?php if ($question): ?>
                <input type="hidden" name="question_id" value="<?php echo esc_attr($question->id); ?>">
                <?php endif; ?>
            </form>
        </div>
        <?php
    }
    
    private function videos_tab() {
        // Verify nonce for action
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET request for action, capability check below
        if (isset($_GET['action'])) {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to perform this action.', 'lucrative-interactive-video'));
            }
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET request for action, capability check above
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        
        if ($action === 'add' || $action === 'edit') {
            $this->video_quiz_form($action);
        } else {
            $this->video_quizzes_list();
        }
    }
    
    private function video_quizzes_list() {
        global $wpdb;
        
        $quizzes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}livq_video_quizzes ORDER BY created_at DESC");
        ?>
        <div class="livq-tab-content">
            <div class="livq-header-actions">
                <h2>Video Quizzes Management</h2>
                <a href="?page=livq-dashboard&tab=videos&action=add" class="button button-primary">
                    <span class="dashicons dashicons-plus"></span>
                    Create Video Quiz
                </a>
            </div>
            
            <div class="livq-table-container">
                <table class="livq-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Video Type</th>
                            <th>Shortcode</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quizzes as $quiz): ?>
                        <tr>
                            <td><?php echo esc_html($quiz->id); ?></td>
                            <td><?php echo esc_html($quiz->title); ?></td>
                            <td>
                                <span class="livq-badge livq-badge-<?php echo esc_attr($quiz->video_type); ?>">
                                    <?php echo esc_html(ucfirst($quiz->video_type)); ?>
                                </span>
                            </td>
                            <td>
                                <code>[livq_quiz id="<?php echo esc_attr($quiz->id); ?>"]</code>
                                <button class="button button-small copy-shortcode" data-shortcode='[livq_quiz id="<?php echo esc_attr($quiz->id); ?>"]'>
                                    Copy
                                </button>
                            </td>
                            <td><?php echo esc_html(gmdate('M j, Y', strtotime($quiz->created_at))); ?></td>
                            <td>
                                <a href="?page=livq-dashboard&tab=videos&action=edit&id=<?php echo esc_attr($quiz->id); ?>" class="button button-small">
                                    Edit
                                </a>
                                <button class="button button-small button-link-delete livq-delete-quiz" data-id="<?php echo esc_attr($quiz->id); ?>">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    private function video_quiz_form($action) {
        // Verify nonce if editing
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET request for ID, capability check below
        if ($action === 'edit' && isset($_GET['id'])) {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to perform this action.', 'lucrative-interactive-video'));
            }
        }
        
        $quiz = null;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET request for ID, capability check above
        if ($action === 'edit' && isset($_GET['id'])) {
            global $wpdb;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET request for ID, capability check above
            $quiz_id = intval($_GET['id']);
            $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}livq_video_quizzes WHERE id = %d", $quiz_id));
            
            // Debug logging
            error_log('=== LIVQ Load Quiz Form Debug ===');
            error_log('Quiz ID: ' . $quiz_id);
            error_log('Quiz data: ' . print_r($quiz, true));
            if ($quiz) {
                error_log('Video Type: ' . $quiz->video_type);
                error_log('Video URL: ' . $quiz->video_url);
                error_log('Time Slots: ' . $quiz->time_slots);
                $time_slots_decoded = json_decode($quiz->time_slots, true);
                error_log('Time Slots Decoded: ' . print_r($time_slots_decoded, true));

                // Fallback: if video_type empty but video_url numeric, assume tutor_lms for display
                if (empty($quiz->video_type) && is_numeric($quiz->video_url)) {
                    $quiz->video_type = 'tutor_lms';
                }
            } else {
                error_log('ERROR: Quiz not found!');
            }
            error_log('=== End Load Quiz Form Debug ===');
        }
        ?>
        <div class="livq-tab-content">
            <div class="livq-header-actions">
                <h2><?php echo $action === 'add' ? 'Create Video Quiz' : 'Edit Video Quiz'; ?></h2>
                <a href="?page=livq-dashboard&tab=videos" class="button">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    Back to Video Quizzes
                </a>
            </div>
            
            <form id="livq-video-quiz-form" class="livq-form">
                <div class="livq-form-group">
                    <label for="quiz_title">Quiz Title *</label>
                    <input type="text" id="quiz_title" name="title" value="<?php echo $quiz ? esc_attr($quiz->title) : ''; ?>" required>
                </div>
                
                <div class="livq-form-group">
                    <label for="video_type">Video Type *</label>
                    <select id="video_type" name="video_type" required>
                        <option value="youtube" <?php echo ($quiz && $quiz->video_type === 'youtube') ? 'selected' : ''; ?>>YouTube</option>
                        <option value="vimeo" <?php echo ($quiz && $quiz->video_type === 'vimeo') ? 'selected' : ''; ?>>Vimeo</option>
                        <option value="mp4" <?php echo ($quiz && $quiz->video_type === 'mp4') ? 'selected' : ''; ?>>MP4 Upload</option>
                        <?php if (function_exists('tutor')): ?>
                        <option value="tutor_lms" <?php echo ($quiz && $quiz->video_type === 'tutor_lms') ? 'selected' : ''; ?>>Tutor LMS Lesson</option>
                        <?php endif; ?>
                    </select>
                    <p class="description">
                        All video types support full quiz functionality with time-based questions.
                    </p>
                </div>
                
                <!-- Tutor LMS Video Selector -->
                <?php if (function_exists('tutor')): ?>
                <div class="livq-form-group" id="tutor-lms-video-selector" style="<?php echo ($quiz && $quiz->video_type === 'tutor_lms') ? '' : 'display: none;'; ?>">
                    <label for="tutor_lms_lesson">Select Tutor LMS Lesson *</label>
                    <select id="tutor_lms_lesson" name="tutor_lms_lesson" style="width: 100%;">
                        <option value="">-- Select a Lesson --</option>
                        <?php
                        // Get the correct post type for Tutor LMS lessons
                        $lesson_post_type = 'lesson'; // Default Tutor LMS lesson post type is 'lesson'
                        if (function_exists('tutor')) {
                            $tutor_instance = tutor();
                            // Tutor LMS stores lesson_post_type as a property
                            if (property_exists($tutor_instance, 'lesson_post_type')) {
                                $lesson_post_type = $tutor_instance->lesson_post_type;
                            }
                        }
                        
                        // Debug: Let's see what post types exist
                        // First, try to get lessons with the determined post type
                        $all_lessons = get_posts(array(
                            'post_type' => $lesson_post_type,
                            'posts_per_page' => -1,
                            'post_status' => 'any', // Include all statuses to see if lessons exist
                            'orderby' => 'title',
                            'order' => 'ASC'
                        ));
                        
                        // If no lessons found, try alternative post type names
                        if (empty($all_lessons)) {
                            $alternative_types = array('tutor_lessons', 'lessons');
                            foreach ($alternative_types as $alt_type) {
                                $alt_lessons = get_posts(array(
                                    'post_type' => $alt_type,
                                    'posts_per_page' => -1,
                                    'post_status' => 'any',
                                    'orderby' => 'title',
                                    'order' => 'ASC'
                                ));
                                if (!empty($alt_lessons)) {
                                    $all_lessons = $alt_lessons;
                                    break;
                                }
                            }
                        }
                        
                        $lessons_with_videos = array();
                        $lessons_without_videos = array();
                        
                        // Check each lesson for video
                        foreach ($all_lessons as $lesson) {
                            $has_video = false;
                            
                            // Method 1: Use Tutor LMS utils function
                            if (function_exists('tutor_utils')) {
                                $video = tutor_utils()->get_video($lesson->ID);
                                if ($video && !empty($video)) {
                                    if (is_array($video)) {
                                        $source = isset($video['source']) ? $video['source'] : '';
                                        if (!empty($source)) {
                                            $has_video = true;
                                        }
                                    } elseif (is_string($video) && !empty(trim($video))) {
                                        $has_video = true;
                                    }
                                }
                            }
                            
                            // Method 2: Check post meta directly
                            if (!$has_video) {
                                $video_meta = get_post_meta($lesson->ID, '_video', true);
                                if ($video_meta && !empty($video_meta)) {
                                    $video_data = maybe_unserialize($video_meta);
                                    if (is_array($video_data) && !empty($video_data)) {
                                        $source = isset($video_data['source']) ? $video_data['source'] : '';
                                        if (!empty($source)) {
                                            $has_video = true;
                                        }
                                    } elseif (is_string($video_data) && !empty(trim($video_data))) {
                                        $has_video = true;
                                    }
                                }
                            }
                            
                            if ($has_video) {
                                $lessons_with_videos[] = $lesson;
                            } else {
                                $lessons_without_videos[] = $lesson;
                            }
                        }
                        
                        // Get saved lesson ID if editing
                        $saved_lesson_id = ($quiz && $quiz->video_type === 'tutor_lms') ? intval($quiz->video_url) : 0;
                        
                        // Display lessons with videos first
                        if (!empty($lessons_with_videos)) {
                            foreach ($lessons_with_videos as $lesson) {
                                $selected = ($saved_lesson_id > 0 && $saved_lesson_id == $lesson->ID) ? 'selected' : '';
                                echo '<option value="' . esc_attr($lesson->ID) . '" ' . $selected . '>' . esc_html($lesson->post_title) . '</option>';
                            }
                        }
                        
                        // Also show lessons without videos (in case video detection failed)
                        if (!empty($lessons_without_videos)) {
                            foreach ($lessons_without_videos as $lesson) {
                                $selected = ($saved_lesson_id > 0 && $saved_lesson_id == $lesson->ID) ? 'selected' : '';
                                echo '<option value="' . esc_attr($lesson->ID) . '" ' . $selected . '>' . esc_html($lesson->post_title) . ' (No video detected)</option>';
                            }
                        }
                        
                        // If saved lesson is not in the list, add it anyway (in case it was deleted or unpublished)
                        if ($saved_lesson_id > 0) {
                            $found_in_list = false;
                            foreach ($all_lessons as $lesson) {
                                if ($lesson->ID == $saved_lesson_id) {
                                    $found_in_list = true;
                                    break;
                                }
                            }
                            if (!$found_in_list) {
                                $saved_lesson = get_post($saved_lesson_id);
                                if ($saved_lesson) {
                                    echo '<option value="' . esc_attr($saved_lesson_id) . '" selected>' . esc_html($saved_lesson->post_title) . ' (Current)</option>';
                                }
                            }
                        }
                        
                        // If no lessons found at all
                        if (empty($all_lessons)) {
                            echo '<option value="" disabled>No Tutor LMS lessons found. Please create lessons in Tutor LMS first.</option>';
                        }
                        ?>
                    </select>
                    <p class="description">
                        Select a Tutor LMS lesson. Lessons with detected videos are shown first. If your lesson has a video but isn't detected, you can still select it (marked as "No video detected").
                    </p>
                    <!-- Separate hidden for lesson ID (used for JS convenience) -->
                    <input type="hidden" id="tutor_lms_lesson_id" value="<?php echo ($quiz && $quiz->video_type === 'tutor_lms') ? esc_attr($quiz->video_url) : ''; ?>">
                </div>
                <?php endif; ?>
                
                <!-- Standard Video URL Input (for YouTube, Vimeo, MP4) -->
                <div class="livq-form-group" id="standard-video-url-input" style="<?php echo ($quiz && $quiz->video_type === 'tutor_lms') ? 'display: none;' : ''; ?>">
                    <label for="video_url_standard">Video URL *</label>
                    <!-- This field is only for user input; the canonical value is stored in #video_url -->
                    <input type="url" id="video_url_standard" value="<?php echo ($quiz && $quiz->video_type !== 'tutor_lms') ? esc_attr($quiz->video_url) : ''; ?>" <?php echo ($quiz && $quiz->video_type === 'tutor_lms') ? '' : 'required'; ?>>
                    <p class="description">
                        <strong>YouTube:</strong> https://www.youtube.com/watch?v=VIDEO_ID<br>
                        <strong>Vimeo:</strong> https://vimeo.com/VIDEO_ID<br>
                        <strong>MP4:</strong> Upload to WordPress Media Library and use the file URL
                    </p>
                </div>
                
                <!-- Hidden canonical video_url field - always present, updated by JS -->
                <input type="hidden" id="video_url" name="video_url" value="<?php echo $quiz ? esc_attr($quiz->video_url) : ''; ?>">
                
                <div class="livq-form-group" id="video-preview-container" style="display: none;">
                    <label>Video Preview</label>
                    <div id="video-preview-wrapper" class="livq-video-preview">
                        <div id="video-preview-placeholder" class="livq-preview-placeholder">
                            <span class="dashicons dashicons-video-alt3"></span>
                            <p>Enter a video URL above to see preview</p>
                        </div>
                    </div>
                    <div id="video-duration-info" class="livq-duration-display" style="display: none;">
                        <strong>📹 Video Duration:</strong> <span id="duration-text">Loading...</span>
                    </div>
                </div>
                
                <div class="livq-form-group">
                    <label>Time Slots & Questions</label>
                    <div id="time-slots-container">
                        <?php if ($quiz): ?>
                            <?php 
                            $time_slots = json_decode($quiz->time_slots, true);
                            if ($time_slots): 
                                foreach ($time_slots as $index => $slot): 
                            ?>
                            <div class="time-slot-item">
                                <div class="time-slot-header">
                                    <label><?php echo esc_html(sprintf('Time Slot %d', $index + 1)); ?></label>
                                    <button type="button" class="remove-time-slot">Remove</button>
                                </div>
                                <div class="time-slot-content">
                                    <div class="time-input-group">
                                        <?php 
                                        // Convert seconds back to original unit for display
                                        $display_time = $slot['time'];
                                        $display_unit = 'seconds';
                                        
                                        // Debug: Check what data we have
                                        // echo "<!-- Debug: Slot data: " . print_r($slot, true) . " -->";
                                        
                                        if (isset($slot['unit'])) {
                                            // Use the stored unit and convert time back
                                            $display_unit = $slot['unit'];
                                            switch ($slot['unit']) {
                                                case 'minutes':
                                                    $display_time = intval($slot['time']) / 60;
                                                    break;
                                                case 'hours':
                                                    $display_time = intval($slot['time']) / 3600;
                                                    break;
                                                case 'seconds':
                                                default:
                                                    $display_time = intval($slot['time']);
                                                    break;
                                            }
                                        } else {
                                            // Fallback: Convert from seconds to appropriate unit for display
                                            $time_seconds = intval($slot['time']);
                                            if ($time_seconds >= 3600 && $time_seconds % 3600 == 0) {
                                                $display_time = $time_seconds / 3600;
                                                $display_unit = 'hours';
                                            } elseif ($time_seconds >= 60 && $time_seconds % 60 == 0) {
                                                $display_time = $time_seconds / 60;
                                                $display_unit = 'minutes';
                                            } else {
                                                $display_time = $time_seconds;
                                                $display_unit = 'seconds';
                                            }
                                        }
                                        
                                        // Ensure display_time is an integer if it's a whole number
                                        if (is_float($display_time) && $display_time == intval($display_time)) {
                                            $display_time = intval($display_time);
                                        }
                                        ?>
                                        <input type="number" name="time_slots[<?php echo esc_attr($index); ?>][time]" value="<?php echo esc_attr($display_time); ?>" placeholder="Time" min="0" class="time-input">
                                        <select name="time_slots[<?php echo esc_attr($index); ?>][unit]" class="time-unit-select">
                                            <option value="seconds" <?php echo $display_unit === 'seconds' ? 'selected' : ''; ?>>Seconds</option>
                                            <option value="minutes" <?php echo $display_unit === 'minutes' ? 'selected' : ''; ?>>Minutes</option>
                                            <option value="hours" <?php echo $display_unit === 'hours' ? 'selected' : ''; ?>>Hours</option>
                                        </select>
                                    </div>
                                    <select name="time_slots[<?php echo esc_attr($index); ?>][questions][]" multiple style="width: 100%; min-height: 100px;">
                                        <?php 
                                        global $wpdb;
                                        $questions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}livq_questions ORDER BY title");
                                        // Ensure questions array exists and is an array
                                        $slot_questions = isset($slot['questions']) && is_array($slot['questions']) ? $slot['questions'] : array();
                                        foreach ($questions as $question): 
                                            // Check if this question is selected - handle both string and integer IDs
                                            $is_selected = in_array($question->id, $slot_questions) || in_array(strval($question->id), $slot_questions) || in_array(intval($question->id), $slot_questions);
                                        ?>
                                        <option value="<?php echo esc_attr($question->id); ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                            <?php echo esc_html($question->title); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <?php endforeach; endif; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="add-time-slot" class="button">Add Time Slot</button>
                </div>
                
                <div class="livq-form-actions">
                    <button type="submit" class="button button-primary">
                        <?php echo $action === 'add' ? 'Create Video Quiz' : 'Update Video Quiz'; ?>
                    </button>
                    <a href="?page=livq-dashboard&tab=videos" class="button">Cancel</a>
                </div>
                
                <?php if ($quiz): ?>
                <input type="hidden" name="quiz_id" value="<?php echo esc_attr($quiz->id); ?>">
                <?php endif; ?>
            </form>
        </div>
        <?php
    }
    
    private function settings_tab() {
        $settings = get_option('livq_settings', array());
        ?>
        <div class="livq-tab-content">
            <h2>Global Settings</h2>
            
            <form id="livq-settings-form" class="livq-form">
                <div class="livq-form-group">
                    <label>
                        <input type="checkbox" name="show_correct_answers" value="1" <?php echo isset($settings['show_correct_answers']) && $settings['show_correct_answers'] ? 'checked' : ''; ?>>
                        <span>Show correct answers after submission</span>
                    </label>
                </div>
                
                <div class="livq-form-group">
                    <label>
                        <input type="checkbox" name="allow_skipping" value="1" <?php echo isset($settings['allow_skipping']) && $settings['allow_skipping'] ? 'checked' : ''; ?>>
                        <span>Allow users to skip questions</span>
                    </label>
                </div>
                
                <div class="livq-form-group">
                    <label for="completion_message">Completion Message</label>
                    <textarea id="completion_message" name="completion_message" rows="3"><?php echo esc_textarea($settings['completion_message'] ?? 'Congratulations! You have completed the video quiz.'); ?></textarea>
                </div>
                
                <div class="livq-form-group">
                    <label for="quiz_theme">Quiz Theme</label>
                    <select id="quiz_theme" name="quiz_theme">
                        <option value="default" <?php echo ($settings['quiz_theme'] ?? 'default') === 'default' ? 'selected' : ''; ?>>Default</option>
                        <option value="modern" <?php echo ($settings['quiz_theme'] ?? 'default') === 'modern' ? 'selected' : ''; ?>>Modern</option>
                        <option value="minimal" <?php echo ($settings['quiz_theme'] ?? 'default') === 'minimal' ? 'selected' : ''; ?>>Minimal</option>
                    </select>
                </div>
                
                <div class="livq-form-section">
                    <h3>Video Player Settings</h3>
                    
                    <div class="livq-form-row">
                        <div class="livq-form-group livq-form-half">
                            <label for="video_width">Video Width</label>
                            <input type="text" id="video_width" name="video_width" 
                                   value="<?php echo esc_attr($settings['video_width'] ?? '100%'); ?>" 
                                   placeholder="100% or 800px">
                            <p class="description">Width in percentage (50%, 60%, 100%) or pixels (800px, 1200px)</p>
                        </div>
                        
                        <div class="livq-form-group livq-form-half">
                            <label for="video_height">Video Height</label>
                            <input type="text" id="video_height" name="video_height" 
                                   value="<?php echo esc_attr($settings['video_height'] ?? '400px'); ?>" 
                                   placeholder="400px or 50vh">
                            <p class="description">Height in pixels (400px) or viewport units (50vh)</p>
                        </div>
                    </div>
                    
                    <div class="livq-form-group">
                        <label for="video_responsive">Responsive Video</label>
                        <select id="video_responsive" name="video_responsive">
                            <option value="1" <?php echo ($settings['video_responsive'] ?? '1') === '1' ? 'selected' : ''; ?>>Yes - Auto adjust to screen size</option>
                            <option value="0" <?php echo ($settings['video_responsive'] ?? '1') === '0' ? 'selected' : ''; ?>>No - Fixed dimensions</option>
                        </select>
                        <p class="description">When enabled, video will automatically resize on mobile devices</p>
                    </div>
                    
                    <div class="livq-form-group">
                        <label for="video_aspect_ratio">Aspect Ratio</label>
                        <select id="video_aspect_ratio" name="video_aspect_ratio">
                            <option value="16:9" <?php echo ($settings['video_aspect_ratio'] ?? '16:9') === '16:9' ? 'selected' : ''; ?>>16:9 (Widescreen)</option>
                            <option value="4:3" <?php echo ($settings['video_aspect_ratio'] ?? '16:9') === '4:3' ? 'selected' : ''; ?>>4:3 (Standard)</option>
                            <option value="21:9" <?php echo ($settings['video_aspect_ratio'] ?? '16:9') === '21:9' ? 'selected' : ''; ?>>21:9 (Ultrawide)</option>
                            <option value="1:1" <?php echo ($settings['video_aspect_ratio'] ?? '16:9') === '1:1' ? 'selected' : ''; ?>>1:1 (Square)</option>
                            <option value="custom" <?php echo ($settings['video_aspect_ratio'] ?? '16:9') === 'custom' ? 'selected' : ''; ?>>Custom (Use width/height above)</option>
                        </select>
                        <p class="description">Choose aspect ratio or use custom dimensions</p>
                    </div>
                </div>
                
                <div class="livq-form-section">
                    <h3>Quiz Container Settings</h3>
                    
                    <div class="livq-form-row">
                        <div class="livq-form-group livq-form-half">
                            <label for="container_width">Quiz Container Width</label>
                            <input type="text" id="container_width" name="container_width" 
                                   value="<?php echo esc_attr($settings['container_width'] ?? '100%'); ?>" 
                                   placeholder="100% or 800px">
                            <p class="description">Width of entire quiz container (50%, 60%, 100%, 800px)</p>
                        </div>
                        
                        <div class="livq-form-group livq-form-half">
                            <label for="container_height">Quiz Container Height</label>
                            <input type="text" id="container_height" name="container_height" 
                                   value="<?php echo esc_attr($settings['container_height'] ?? 'auto'); ?>" 
                                   placeholder="auto or 600px">
                            <p class="description">Height of entire quiz container (auto, 600px, 50vh)</p>
                        </div>
                    </div>
                    
                    <div class="livq-form-group">
                        <label for="container_max_width">Maximum Container Width</label>
                            <input type="text" id="container_max_width" name="container_max_width" 
                                   value="<?php echo esc_attr($settings['container_max_width'] ?? '1200px'); ?>" 
                                   placeholder="1200px or 90%">
                            <p class="description">Maximum width to prevent quiz from being too wide on large screens</p>
                    </div>
                    
                    <div class="livq-form-group">
                        <label for="container_alignment">Container Alignment</label>
                        <select id="container_alignment" name="container_alignment">
                            <option value="center" <?php echo ($settings['container_alignment'] ?? 'center') === 'center' ? 'selected' : ''; ?>>Center</option>
                            <option value="left" <?php echo ($settings['container_alignment'] ?? 'center') === 'left' ? 'selected' : ''; ?>>Left</option>
                            <option value="right" <?php echo ($settings['container_alignment'] ?? 'center') === 'right' ? 'selected' : ''; ?>>Right</option>
                        </select>
                        <p class="description">How to align the quiz container on the page</p>
                    </div>
                </div>
                
                <div class="livq-form-actions">
                    <button type="submit" class="button button-primary">Save Settings</button>
                </div>
            </form>
        </div>
        <?php
    }
    
    private function reports_tab() {
        global $wpdb;
        
        // Ensure required tables exist to prevent errors
        $results_table = $wpdb->prefix . 'livq_quiz_results';
        $videos_table = $wpdb->prefix . 'livq_video_quizzes';
        
        $have_results_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $results_table)) === $results_table;
        $have_videos_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $videos_table)) === $videos_table;
        
        if (!$have_results_table || !$have_videos_table) {
            if (class_exists('LIVQ_Database')) {
                $db = new LIVQ_Database();
                $db->create_tables();
            }
        }
        
        // Re-check after attempted creation
        $have_results_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $results_table)) === $results_table;
        $have_videos_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $videos_table)) === $videos_table;
        
        $results = array();
        if ($have_results_table && $have_videos_table) {
            $results = $wpdb->get_results("
                SELECT r.*, v.title as quiz_title 
                FROM {$wpdb->prefix}livq_quiz_results r 
                LEFT JOIN {$wpdb->prefix}livq_video_quizzes v ON r.quiz_id = v.id 
                ORDER BY r.completed_at DESC 
                LIMIT 50
            ");
        }
        ?>
        <div class="livq-tab-content">
            <h2>Quiz Reports</h2>
            
            <div class="livq-table-container">
                <table class="livq-table">
                    <thead>
                        <tr>
                            <th>Quiz</th>
                            <th>User</th>
                            <th>Score</th>
                            <th>Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                        <tr>
                            <td><?php echo esc_html($result->quiz_title); ?></td>
                            <td>
                        <?php if ($result->user_id): ?>
                            <?php $user = get_user_by('id', intval($result->user_id)); ?>
                            <?php echo $user ? esc_html($user->display_name) : esc_html('User #' . intval($result->user_id)); ?>
                                <?php else: ?>
                            Guest (<?php echo esc_html($result->user_ip); ?>)
                                <?php endif; ?>
                            </td>
                    <td><?php echo esc_html(intval($result->score)); ?>/<?php echo esc_html(intval($result->total_questions)); ?></td>
                            <td><?php echo esc_html(gmdate('M j, Y H:i', strtotime($result->completed_at))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    // AJAX handlers
    public function save_question() {
        check_ajax_referer('livq_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'lucrative-interactive-video'));
        }
        
        global $wpdb;
        
        // Check free plugin limits
        $questions_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}livq_questions");
        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        
        // If creating new question (not editing existing)
        if (!$question_id && $questions_count >= 10) {
            wp_send_json_error('Free plugin limit reached. Maximum 10 questions allowed. Upgrade to Pro for unlimited questions.');
        }
        
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';
        
        // Validate required fields
        if (empty($title)) {
            wp_send_json_error(__('Question title is required.', 'lucrative-interactive-video'));
        }
        if (empty($type)) {
            wp_send_json_error(__('Question type is required.', 'lucrative-interactive-video'));
        }
        
        $options = null;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in loop below
        if (isset($_POST['options']) && is_array($_POST['options'])) {
            $sanitized_options = array();
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in loop below
            $raw_options = wp_unslash($_POST['options']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            foreach ($raw_options as $opt) {
                $sanitized_opt = sanitize_text_field($opt);
                if ($sanitized_opt !== '') {
                    $sanitized_options[] = $sanitized_opt;
                }
            }
            $options = wp_json_encode($sanitized_options);
        }
        $correct_answer = isset($_POST['correct_answer']) ? sanitize_text_field(wp_unslash($_POST['correct_answer'])) : '';
        $explanation = isset($_POST['explanation']) ? sanitize_textarea_field(wp_unslash($_POST['explanation'])) : '';
        

        
        // Process match-image-label type (basic validation - full processing in PRO addon)
        if ($type === 'match_image_label') {
            if (!isset($_POST['match_image_urls']) || !is_array($_POST['match_image_urls']) || empty($_POST['match_image_urls'])) {
                wp_send_json_error(__('Please add at least one image for the match image to label question.', 'lucrative-interactive-video'));
            }
            if (!isset($_POST['match_image_labels']) || !is_array($_POST['match_image_labels']) || empty($_POST['match_image_labels'])) {
                wp_send_json_error(__('Please add labels for all images.', 'lucrative-interactive-video'));
            }
        }
        
        $metadata = '';
        if (isset($_POST['metadata']) && is_array($_POST['metadata'])) {
            $raw_metadata = wp_unslash($_POST['metadata']);
            $sanitized_metadata = array();
            
            // Specifically sanitize branching logic if present
            if (isset($raw_metadata['branching']) && is_array($raw_metadata['branching'])) {
                $sanitized_metadata['branching'] = array(
                    'correct_jump' => isset($raw_metadata['branching']['correct_jump']) ? intval($raw_metadata['branching']['correct_jump']) : '',
                    'incorrect_jump' => isset($raw_metadata['branching']['incorrect_jump']) ? intval($raw_metadata['branching']['incorrect_jump']) : ''
                );
            }
            
            $metadata = wp_json_encode($sanitized_metadata);
        }
        
        // Allow filters to modify data before saving
        $data = apply_filters('livq_before_save_question', array(
            'title' => $title,
            'type' => $type,
            'options' => $options,
            'correct_answer' => $correct_answer,
            'explanation' => $explanation,
            'metadata' => $metadata
        ), $_POST);
        
        if ($question_id) {
            $wpdb->update($wpdb->prefix . 'livq_questions', $data, array('id' => $question_id));
            $message = 'Question updated successfully!';
        } else {
            $wpdb->insert($wpdb->prefix . 'livq_questions', $data);
            $message = 'Question added successfully!';
        }
        
        if ($wpdb->last_error) {
            // Try to update database schema if error is related to column size
            if (strpos($wpdb->last_error, 'correct_answer') !== false || strpos($wpdb->last_error, 'too long') !== false) {
                if (class_exists('LIVQ_Database')) {
                    $database = new LIVQ_Database();
                    $database->update_tables();
                    
                    // Retry the save operation
                    if ($question_id) {
                        $wpdb->update($wpdb->prefix . 'livq_questions', $data, array('id' => $question_id));
                    } else {
                        $wpdb->insert($wpdb->prefix . 'livq_questions', $data);
                    }
                    
                    // Check again after update
                    if (!$wpdb->last_error) {
                        wp_send_json_success($message);
                        return;
                    }
                }
            }
            wp_send_json_error('Database error: ' . $wpdb->last_error);
        }
        
        wp_send_json_success($message);
    }
    
    public function delete_question() {
        check_ajax_referer('livq_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'lucrative-interactive-video'));
        }
        
        global $wpdb;
        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        if ($question_id <= 0) {
            wp_send_json_error(__('Invalid question ID.', 'lucrative-interactive-video'));
        }
        
        $wpdb->delete($wpdb->prefix . 'livq_questions', array('id' => $question_id));
        
        wp_send_json_success('Question deleted successfully!');
    }
    
    public function save_video_quiz() {
        check_ajax_referer('livq_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'lucrative-interactive-video'));
        }
        
        global $wpdb;
        
        // Enable error logging
        error_log('=== LIVQ Save Video Quiz Debug ===');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Check free plugin limits
        $quizzes_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}livq_video_quizzes");
        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
        
        error_log('Quiz ID: ' . $quiz_id);
        error_log('Existing quizzes count: ' . $quizzes_count);
        
        // If creating new video quiz (not editing existing)
        if (!$quiz_id && $quizzes_count >= 5) {
            wp_send_json_error('Free plugin limit reached. Maximum 5 video quizzes allowed. Upgrade to Pro for unlimited video quizzes.');
        }
        
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $video_type = isset($_POST['video_type']) ? sanitize_text_field(wp_unslash($_POST['video_type'])) : '';
        
        error_log('Title: ' . $title);
        error_log('Video Type: ' . $video_type);
        
        // Handle Tutor LMS videos - video_url is the lesson ID
        if ($video_type === 'tutor_lms') {
            // Check for tutor_lms_lesson first, then fallback to video_url (for backward compatibility)
            $tutor_lms_lesson = isset($_POST['tutor_lms_lesson']) ? intval($_POST['tutor_lms_lesson']) : 0;
            error_log('Tutor LMS Lesson from POST[tutor_lms_lesson]: ' . $tutor_lms_lesson);
            if ($tutor_lms_lesson <= 0 && isset($_POST['video_url'])) {
                $tutor_lms_lesson = intval($_POST['video_url']);
                error_log('Tutor LMS Lesson from POST[video_url]: ' . $tutor_lms_lesson);
            }
            if ($tutor_lms_lesson <= 0) {
                error_log('ERROR: No Tutor LMS lesson selected');
                wp_send_json_error(__('Please select a Tutor LMS lesson.', 'lucrative-interactive-video'));
            }
            $video_url = strval($tutor_lms_lesson); // Store lesson ID as string
            error_log('Final video_url (lesson ID): ' . $video_url);
        } else {
        $video_url = isset($_POST['video_url']) ? esc_url_raw(wp_unslash($_POST['video_url'])) : '';
            error_log('Video URL: ' . $video_url);
        }
        
        // Process time slots and convert units to seconds
        $time_slots = array();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in process_time_slots()
        if (isset($_POST['time_slots']) && is_array($_POST['time_slots'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in process_time_slots()
            $raw_time_slots = wp_unslash($_POST['time_slots']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            error_log('Raw time slots: ' . print_r($raw_time_slots, true));
            $time_slots = $this->process_time_slots($raw_time_slots);
            error_log('Processed time slots: ' . print_r($time_slots, true));
        } else {
            error_log('WARNING: No time_slots in POST data');
        }
        $time_slots_json = wp_json_encode($time_slots);
        error_log('Time slots JSON: ' . $time_slots_json);
        
        $data = array(
            'title' => $title,
            'video_type' => $video_type,
            'video_url' => $video_url,
            'time_slots' => $time_slots_json,
            'shortcode' => 'livq_' . uniqid()
        );
        
        error_log('Data to save: ' . print_r($data, true));
        
        if ($quiz_id) {
            unset($data['shortcode']); // Don't change existing shortcode
            error_log('Updating quiz ID: ' . $quiz_id);
            $result = $wpdb->update($wpdb->prefix . 'livq_video_quizzes', $data, array('id' => $quiz_id));
            
            if ($result === false) {
                error_log('ERROR: Update failed. Last error: ' . $wpdb->last_error);
                error_log('Last query: ' . $wpdb->last_query);
                wp_send_json_error('Failed to update video quiz. Database error: ' . $wpdb->last_error);
                return;
            }
            
            error_log('Update successful. Rows affected: ' . $result);
            
            // Verify the update
            $updated_quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}livq_video_quizzes WHERE id = %d", $quiz_id));
            error_log('Updated quiz data: ' . print_r($updated_quiz, true));
            
            $message = 'Video quiz updated successfully!';
        } else {
            error_log('Inserting new quiz');
            $result = $wpdb->insert($wpdb->prefix . 'livq_video_quizzes', $data);
            
            if ($result === false) {
                error_log('ERROR: Insert failed. Last error: ' . $wpdb->last_error);
                error_log('Last query: ' . $wpdb->last_query);
                wp_send_json_error('Failed to create video quiz. Database error: ' . $wpdb->last_error);
                return;
            }
            
            error_log('Insert successful. New ID: ' . $wpdb->insert_id);
            $message = 'Video quiz created successfully!';
        }
        
        error_log('=== End Save Video Quiz Debug ===');
        wp_send_json_success($message);
    }
    
    /**
     * Process time slots and convert units to seconds
     */
    private function process_time_slots($time_slots) {
        $processed_slots = array();
        
        foreach ($time_slots as $slot) {
            $time = isset($slot['time']) ? intval($slot['time']) : 0;
            $unit = isset($slot['unit']) ? sanitize_text_field($slot['unit']) : 'seconds';
            $questions = array();
            if (isset($slot['questions']) && is_array($slot['questions'])) {
                foreach ($slot['questions'] as $qid) {
                    $questions[] = intval($qid);
                }
            }
            
            // Convert to seconds based on unit
            switch ($unit) {
                case 'minutes':
                    $time_in_seconds = $time * 60;
                    break;
                case 'hours':
                    $time_in_seconds = $time * 3600;
                    break;
                case 'seconds':
                default:
                    $time_in_seconds = $time;
                    break;
            }
            
            $processed_slots[] = array(
                'time' => $time_in_seconds,
                'unit' => $unit, // Keep original unit for display
                'questions' => $questions
            );
        }
        
        return $processed_slots;
    }
    
    public function delete_video_quiz() {
        check_ajax_referer('livq_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'lucrative-interactive-video'));
        }
        
        global $wpdb;
        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
        if ($quiz_id <= 0) {
            wp_send_json_error(__('Invalid quiz ID.', 'lucrative-interactive-video'));
        }
        
        $wpdb->delete($wpdb->prefix . 'livq_video_quizzes', array('id' => $quiz_id));
        
        wp_send_json_success('Video quiz deleted successfully!');
    }
    
    public function save_settings() {
        check_ajax_referer('livq_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'lucrative-interactive-video'));
        }
        
        $settings = array(
            'show_correct_answers' => isset($_POST['show_correct_answers']),
            'allow_skipping' => isset($_POST['allow_skipping']),
            'completion_message' => isset($_POST['completion_message']) ? sanitize_textarea_field(wp_unslash($_POST['completion_message'])) : '',
            'quiz_theme' => isset($_POST['quiz_theme']) ? sanitize_text_field(wp_unslash($_POST['quiz_theme'])) : 'default'
        );
        
        update_option('livq_settings', $settings);
        
        wp_send_json_success('Settings saved successfully!');
    }
    
    public function get_questions() {
        check_ajax_referer('livq_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'lucrative-interactive-video'));
        }
        
        global $wpdb;
        $questions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}livq_questions ORDER BY title");
        
        $formatted_questions = array();
        foreach ($questions as $question) {
            $formatted_questions[] = array(
                'id' => $question->id,
                'title' => $question->title,
                'type' => $question->type
            );
        }
        
        // Debug removed for production
        
        wp_send_json_success($formatted_questions);
    }
    
    public function get_tutor_lms_video() {
        check_ajax_referer('livq_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'lucrative-interactive-video'));
        }
        
        if (!function_exists('tutor_utils')) {
            wp_send_json_error(__('Tutor LMS is not installed or activated.', 'lucrative-interactive-video'));
        }
        
        $lesson_id = isset($_POST['lesson_id']) ? intval($_POST['lesson_id']) : 0;
        if ($lesson_id <= 0) {
            wp_send_json_error(__('Invalid lesson ID.', 'lucrative-interactive-video'));
        }
        
        // Get video data from Tutor LMS
        $video = tutor_utils()->get_video($lesson_id);
        
        if (!$video || empty($video)) {
            wp_send_json_error(__('This lesson does not have a video.', 'lucrative-interactive-video'));
        }
        
        $video_source = isset($video['source']) ? $video['source'] : '';
        $video_url = '';
        
        // Extract video URL based on source
        if ($video_source === 'youtube') {
            $video_url = isset($video['source_youtube']) ? $video['source_youtube'] : '';
        } elseif ($video_source === 'vimeo') {
            $video_url = isset($video['source_vimeo']) ? $video['source_vimeo'] : '';
        } elseif ($video_source === 'html5') {
            $video_url = isset($video['source_html5']) ? wp_get_attachment_url($video['source_video_id']) : '';
        } elseif ($video_source === 'external_link') {
            $video_url = isset($video['source_external_url']) ? $video['source_external_url'] : '';
        }
        
        if (empty($video_url)) {
            wp_send_json_error(__('Could not extract video URL from lesson.', 'lucrative-interactive-video'));
        }
        
        wp_send_json_success(array(
            'video_url' => $video_url,
            'video_source' => $video_source,
            'lesson_id' => $lesson_id,
            'lesson_title' => get_the_title($lesson_id)
        ));
    }
    
    public function get_video_duration() {
        check_ajax_referer('livq_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'lucrative-interactive-video'));
        }
        
        $video_url = isset($_POST['video_url']) ? sanitize_url(wp_unslash($_POST['video_url'])) : '';
        $video_type = isset($_POST['video_type']) ? sanitize_text_field(wp_unslash($_POST['video_type'])) : '';
        
        if (empty($video_url) || empty($video_type)) {
            wp_send_json_error('Invalid video URL or type');
        }
        
        $duration = $this->detect_video_duration($video_url, $video_type);
        
        if ($duration > 0) {
            wp_send_json_success(array('duration' => $duration));
        } else {
            wp_send_json_error('Unable to detect video duration');
        }
    }
    
    private function detect_video_duration($video_url, $video_type) {
        switch ($video_type) {
            case 'youtube':
                return $this->get_youtube_duration($video_url);
            case 'vimeo':
                return $this->get_vimeo_duration($video_url);
            case 'mp4':
                return $this->get_mp4_duration($video_url);
            default:
                return 0;
        }
    }
    
    private function get_youtube_duration($video_url) {
        // Extract YouTube video ID
        $video_id = $this->extract_youtube_id($video_url);
        if (!$video_id) {
            return 0;
        }
        
        // Use YouTube Data API v3
        $api_key = 'AIzaSyBvOkBwJcJUWjN8xV8xV8xV8xV8xV8xV8xV'; // You'll need to get a real API key
        $api_url = "https://www.googleapis.com/youtube/v3/videos?id={$video_id}&part=contentDetails&key={$api_key}";
        
        $response = wp_remote_get($api_url);
        if (is_wp_error($response)) {
            return 0;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['items'][0]['contentDetails']['duration'])) {
            return $this->parse_iso8601_duration($data['items'][0]['contentDetails']['duration']);
        }
        
        return 0;
    }
    
    private function get_vimeo_duration($video_url) {
        // Extract Vimeo video ID
        $video_id = $this->extract_vimeo_id($video_url);
        if (!$video_id) {
            return 0;
        }
        
        // Use Vimeo API
        $api_url = "https://vimeo.com/api/v2/video/{$video_id}.json";
        
        $response = wp_remote_get($api_url);
        if (is_wp_error($response)) {
            return 0;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data[0]['duration'])) {
            return intval($data[0]['duration']);
        }
        
        return 0;
    }
    
    private function get_mp4_duration($video_url) {
        // For MP4 files, we need to use a different approach
        // This is a simplified version - in production, you might want to use FFmpeg
        $response = wp_remote_head($video_url);
        if (is_wp_error($response)) {
            return 0;
        }
        
        $headers = wp_remote_retrieve_headers($response);
        $content_length = $headers['content-length'] ?? 0;
        
        // This is a rough estimate - in reality, you'd need to parse the MP4 file
        // For now, we'll return 0 to indicate we can't determine duration
        return 0;
    }
    
    private function extract_youtube_id($url) {
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function extract_vimeo_id($url) {
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function parse_iso8601_duration($duration) {
        // Parse ISO 8601 duration format (e.g., PT4M13S)
        $interval = new DateInterval($duration);
        return ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }
    
    /**
     * Pro Features Page
     */
    public function pro_features_page() {
        ?>
        <div class="wrap">
            <div class="livq-pro-features-page">
            <div class="livq-pro-header">
                <div class="livq-pro-hero">
                    <h1>🚀 Upgrade to Pro</h1>
                    <p class="livq-pro-tagline">Unlock the full potential of interactive video quizzes</p>
                    <div class="livq-pro-badge">
                        <span class="dashicons dashicons-star-filled"></span>
                        Premium Features
                    </div>
                </div>
            </div>
            
            <div class="livq-pro-content">
                <!-- Pricing Section -->
                <div class="livq-pro-section">
                    <h2>💰 Pricing Plans</h2>
                    <div class="livq-pricing-grid">
                        <div class="livq-pricing-card">
                            <div class="livq-pricing-header">
                                <h3>Pro Annual</h3>
                                <div class="livq-price">
                                    <span class="livq-currency">$</span>
                                    <span class="livq-amount">59</span>
                                    <span class="livq-period">/year</span>
                                </div>
                            </div>
                            <ul class="livq-features-list">
                                <li>✅ Unlimited Questions</li>
                                <li>✅ Unlimited Video Quizzes</li>
                                <li>✅ Advanced Question Types</li>
                                <li>✅ Detailed Reports</li>
                                <li>✅ LMS Integration</li>
                                <li>✅ Priority Support</li>
                            </ul>
                            <button class="livq-upgrade-btn">Upgrade to Pro</button>
                        </div>
                        
                        <div class="livq-pricing-card livq-popular">
                            <div class="livq-popular-badge">Most Popular</div>
                            <div class="livq-pricing-header">
                                <h3>Pro Lifetime</h3>
                                <div class="livq-price">
                                    <span class="livq-currency">$</span>
                                    <span class="livq-amount">199</span>
                                    <span class="livq-period">one-time</span>
                                </div>
                            </div>
                            <ul class="livq-features-list">
                                <li>✅ Everything in Pro Annual</li>
                                <li>✅ Lifetime Updates</li>
                                <li>✅ No Recurring Fees</li>
                                <li>✅ White-label License</li>
                                <li>✅ Agency License</li>
                                <li>✅ Premium Support</li>
                            </ul>
                            <button class="livq-upgrade-btn livq-primary">Get Lifetime</button>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Features -->
                <div class="livq-pro-section">
                    <h2>🎯 Advanced Question Types</h2>
                    <div class="livq-features-grid">
                        <div class="livq-feature-card">
                            <div class="livq-feature-icon">📝</div>
                            <h3>Short Answer</h3>
                            <p>Allow users to type detailed answers with text matching and keyword detection.</p>
                        </div>
                        
                        <div class="livq-feature-card">
                            <div class="livq-feature-icon">🔤</div>
                            <h3>Fill in the Blanks</h3>
                            <p>Create interactive fill-in-the-blank questions with multiple correct answers.</p>
                        </div>
                        
                        <div class="livq-feature-card">
                            <div class="livq-feature-icon">🔗</div>
                            <h3>Match the Pair</h3>
                            <p>Drag and drop matching questions for better engagement and learning.</p>
                        </div>
                        
                        <div class="livq-feature-card">
                            <div class="livq-feature-icon">🎯</div>
                            <h3>Drag & Drop</h3>
                            <p>Interactive drag and drop questions with visual feedback and animations.</p>
                        </div>
                    </div>
                </div>
                
                <!-- LMS Integration -->
                <div class="livq-pro-section">
                    <h2>🎓 LMS Integration</h2>
                    <div class="livq-lms-grid">
                        <div class="livq-lms-item">
                            <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiByeD0iOCIgZmlsbD0iIzAwN2NiYSIvPgo8cGF0aCBkPSJNMTIgMTZIMjhWMTJIMTJWMTZaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTIgMjBIMjhWMTZIMTJWMjBaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTIgMjRIMjhWMjBIMTJWMjRaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K" alt="LearnDash">
                            <h4>LearnDash</h4>
                            <p>Full integration with LearnDash course progress and certificates.</p>
                        </div>
                        
                        <div class="livq-lms-item">
                            <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiByeD0iOCIgZmlsbD0iIzAwN2NiYSIvPgo8cGF0aCBkPSJNMTIgMTZIMjhWMTJIMTJWMTZaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTIgMjBIMjhWMTZIMTJWMjBaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTIgMjRIMjhWMjBIMTJWMjRaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K" alt="TutorLMS">
                            <h4>TutorLMS</h4>
                            <p>Seamless integration with TutorLMS assignments and grading.</p>
                        </div>
                        
                        <div class="livq-lms-item">
                            <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiByeD0iOCIgZmlsbD0iIzAwN2NiYSIvPgo8cGF0aCBkPSJNMTIgMTZIMjhWMTJIMTJWMTZaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTIgMjBIMjhWMTZIMTJWMjBaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTIgMjRIMjhWMjBIMTJWMjRaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K" alt="LifterLMS">
                            <h4>LifterLMS</h4>
                            <p>Complete integration with LifterLMS course completion tracking.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Analytics & Reports -->
                <div class="livq-pro-section">
                    <h2>📊 Advanced Analytics & Reports</h2>
                    <div class="livq-analytics-grid">
                        <div class="livq-analytics-card">
                            <div class="livq-analytics-icon">📈</div>
                            <h3>Detailed Reports</h3>
                            <ul>
                                <li>Individual student progress</li>
                                <li>Question difficulty analysis</li>
                                <li>Time spent on each question</li>
                                <li>Completion rates and trends</li>
                            </ul>
                        </div>
                        
                        <div class="livq-analytics-card">
                            <div class="livq-analytics-icon">📊</div>
                            <h3>Export Options</h3>
                            <ul>
                                <li>CSV export for data analysis</li>
                                <li>PDF reports for presentations</li>
                                <li>Excel integration</li>
                                <li>API access for custom reports</li>
                            </ul>
                        </div>
                        
                        <div class="livq-analytics-card">
                            <div class="livq-analytics-icon">🎯</div>
                            <h3>Progress Tracking</h3>
                            <ul>
                                <li>Real-time progress monitoring</li>
                                <li>Student dashboard</li>
                                <li>Instructor notifications</li>
                                <li>Automated follow-ups</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Gamification -->
                <div class="livq-pro-section">
                    <h2>🎮 Gamification Features</h2>
                    <div class="livq-gamification-grid">
                        <div class="livq-gamification-item">
                            <div class="livq-gamification-icon">🏆</div>
                            <h3>Leaderboards</h3>
                            <p>Create competitive leaderboards to motivate students and increase engagement.</p>
                        </div>
                        
                        <div class="livq-gamification-item">
                            <div class="livq-gamification-icon">🎖️</div>
                            <h3>Badges & Certificates</h3>
                            <p>Award digital badges and certificates for quiz completion and achievements.</p>
                        </div>
                        
                        <div class="livq-gamification-item">
                            <div class="livq-gamification-icon">⭐</div>
                            <h3>Points System</h3>
                            <p>Implement points, streaks, and achievement systems to gamify learning.</p>
                        </div>
                        
                        <div class="livq-gamification-item">
                            <div class="livq-gamification-icon">🎯</div>
                            <h3>Progress Bars</h3>
                            <p>Visual progress indicators and completion tracking for better motivation.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Settings -->
                <div class="livq-pro-section">
                    <h2>⚙️ Advanced Settings</h2>
                    <div class="livq-settings-grid">
                        <div class="livq-setting-item">
                            <h3>⏱️ Timer Controls</h3>
                            <p>Set time limits for individual questions, entire quizzes, or specific sections.</p>
                        </div>
                        
                        <div class="livq-setting-item">
                            <h3>📧 Email Notifications</h3>
                            <p>Automated email notifications for quiz completion, results, and reminders.</p>
                        </div>
                        
                        <div class="livq-setting-item">
                            <h3>🔄 Resume Functionality</h3>
                            <p>Allow students to resume quizzes from where they left off.</p>
                        </div>
                        
                        <div class="livq-setting-item">
                            <h3>🎨 Custom Themes</h3>
                            <p>Multiple quiz themes and custom styling options for branding.</p>
                        </div>
                        
                        <div class="livq-setting-item">
                            <h3>🌍 Multi-language</h3>
                            <p>Full RTL support and multi-language interface for global reach.</p>
                        </div>
                        
                        <div class="livq-setting-item">
                            <h3>🔒 Access Controls</h3>
                            <p>User role restrictions, password protection, and scheduled availability.</p>
                        </div>
                    </div>
                </div>
                
                <!-- CTA Section -->
                <div class="livq-pro-cta">
                    <div class="livq-cta-content">
                        <h2>Ready to Transform Your Videos?</h2>
                        <p>Join thousands of educators and course creators who are already using Pro features to create engaging, interactive learning experiences.</p>
                        <div class="livq-cta-buttons">
                            <button class="livq-cta-primary">Upgrade to Pro Now</button>
                            <button class="livq-cta-secondary">View Demo</button>
                        </div>
                        <div class="livq-guarantee">
                            <span class="dashicons dashicons-shield"></span>
                            <span>30-day money-back guarantee</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    }
}

