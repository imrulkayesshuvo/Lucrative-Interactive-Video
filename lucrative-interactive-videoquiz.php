<?php
/**
 * Plugin Name: Lucrative Interactive VideoQuiz
 * Plugin URI: https://wordpress.org/plugins/lucrative-interactive-videoquiz
 * Description: Make your videos smarter — engage learners with in-video questions. Create interactive video quizzes with pause-and-answer functionality.
 * Version: 1.0.1
 * Author: Zahid Hasan
 * Author URI: https://wordpress.org
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lucrative-interactive-videoquiz
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LIVQ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LIVQ_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('LIVQ_VERSION', '1.0.2');

// Main plugin class
class LucrativeInteractiveVideoQuiz {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain - WordPress will automatically load translations if plugin is hosted on WordPress.org
        // load_plugin_textdomain('lucrative-interactive-videoquiz', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize components
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once LIVQ_PLUGIN_PATH . 'includes/class-database.php';
        require_once LIVQ_PLUGIN_PATH . 'includes/class-dashboard.php';
        require_once LIVQ_PLUGIN_PATH . 'includes/class-question-manager.php';
        require_once LIVQ_PLUGIN_PATH . 'includes/class-video-manager.php';
        require_once LIVQ_PLUGIN_PATH . 'includes/class-shortcode.php';
        require_once LIVQ_PLUGIN_PATH . 'includes/class-frontend.php';
        require_once LIVQ_PLUGIN_PATH . 'includes/class-ajax.php';
    }
    
    private function init_hooks() {
        // Initialize dashboard
        new LIVQ_Dashboard();
        
        // Initialize question manager
        new LIVQ_Question_Manager();
        
        // Initialize video manager
        new LIVQ_Video_Manager();
        
        // Initialize shortcode system
        new LIVQ_Shortcode();
        
        // Initialize frontend
        new LIVQ_Frontend();
        
        // Initialize AJAX handlers
        new LIVQ_Ajax();
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Add Settings link in Plugins list
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        // Optional: extra links like Docs
        add_filter('plugin_row_meta', array($this, 'add_row_meta_links'), 10, 2);
    }
    
    public function enqueue_frontend_scripts() {
        wp_enqueue_script('jquery');
        
        // Plyr.js CSS
        wp_enqueue_style(
            'plyr-css',
            'https://cdn.plyr.io/3.7.8/plyr.css',
            array(),
            '3.7.8'
        );
        
        // Plyr.js JavaScript
        wp_enqueue_script(
            'plyr-js',
            'https://cdn.plyr.io/3.7.8/plyr.js',
            array(),
            '3.7.8',
            true
        );
        
        wp_enqueue_script('livq-frontend', LIVQ_PLUGIN_URL . 'assets/js/frontend.js', array('jquery', 'plyr-js'), LIVQ_VERSION, true);
        wp_enqueue_style('livq-frontend', LIVQ_PLUGIN_URL . 'assets/css/frontend.css', array('plyr-css'), LIVQ_VERSION);
        
        // Localize script for AJAX
        wp_localize_script('livq-frontend', 'livq_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('livq_nonce')
        ));
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'livq-dashboard') !== false || strpos($hook, 'livq-pro-features') !== false) {
            wp_enqueue_script('livq-admin', LIVQ_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), LIVQ_VERSION, true);
            wp_enqueue_style('livq-admin', LIVQ_PLUGIN_URL . 'assets/css/admin.css', array(), LIVQ_VERSION);
            
            // Enqueue YouTube API for video preview
            wp_enqueue_script('youtube-api', 'https://www.youtube.com/iframe_api', array(), '1.1', true);
            
            // Enqueue Vimeo API for video preview
            wp_enqueue_script('vimeo-api', 'https://player.vimeo.com/api/player.js', array(), '2.1', true);
            
            // Localize script for AJAX
            wp_localize_script('livq-admin', 'livq_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('livq_admin_nonce')
            ));
        }
    }

    /**
     * Add Settings link to plugin actions (Plugins → Installed Plugins)
     */
    public function add_settings_link(array $links) {
        $settings_url = admin_url('admin.php?page=livq-dashboard');
        $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'lucrative-interactive-videoquiz') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Add additional meta links under plugin description
     */
    public function add_row_meta_links(array $links, $file) {
        if ($file !== plugin_basename(__FILE__)) {
            return $links;
        }
        $docs_url = admin_url('admin.php?page=livq-dashboard&tab=documentation');
        $links[] = '<a href="' . esc_url($docs_url) . '">' . esc_html__('Documentation', 'lucrative-interactive-videoquiz') . '</a>';
        return $links;
    }
    
    public function activate() {
        // Load dependencies first
        $this->load_dependencies();
        
        // Create database tables
        $database = new LIVQ_Database();
        $database->create_tables();
        
        // Set default options
        $this->set_default_options();
    }
    
    public function deactivate() {
        // Clean up if needed
    }
    
    private function set_default_options() {
        $default_options = array(
            'show_correct_answers' => true,
            'allow_skipping' => false,
            'completion_message' => 'Congratulations! You have completed the video quiz.',
            'quiz_theme' => 'default'
        );
        
        add_option('livq_settings', $default_options);
    }
}

// Initialize the plugin
new LucrativeInteractiveVideoQuiz();
