<?php
/**
 * Database management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class LIVQ_Database {
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Questions table
        $questions_table = $wpdb->prefix . 'livq_questions';
        $questions_sql = "CREATE TABLE $questions_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            type enum('true_false', 'multiple_choice') NOT NULL,
            options longtext,
            correct_answer varchar(255) NOT NULL,
            explanation text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Video quizzes table
        $video_quizzes_table = $wpdb->prefix . 'livq_video_quizzes';
        $video_quizzes_sql = "CREATE TABLE $video_quizzes_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            video_url varchar(500) NOT NULL,
            video_type enum('youtube', 'vimeo', 'mp4', 'tutor_lms') NOT NULL,
            time_slots longtext,
            settings longtext,
            shortcode varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY shortcode (shortcode)
        ) $charset_collate;";
        
        // Quiz results table
        $quiz_results_table = $wpdb->prefix . 'livq_quiz_results';
        $quiz_results_sql = "CREATE TABLE $quiz_results_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            quiz_id int(11) NOT NULL,
            user_id int(11),
            user_ip varchar(45),
            answers longtext,
            score int(11) NOT NULL,
            total_questions int(11) NOT NULL,
            completed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY quiz_id (quiz_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($questions_sql);
        dbDelta($video_quizzes_sql);
        dbDelta($quiz_results_sql);
        
        // Update existing tables if needed
        $this->update_tables();
    }
    
    /**
     * Update existing database tables for new features
     */
    public function update_tables() {
        global $wpdb;
        
        $questions_table = $wpdb->prefix . 'livq_questions';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $questions_table
        ));
        
        if (!$table_exists) {
            return; // Table doesn't exist, will be created by create_tables()
        }
        
        // Check if correct_answer column exists and is VARCHAR
        $column_info = $wpdb->get_row($wpdb->prepare(
            "SELECT COLUMN_TYPE, COLUMN_NAME 
             FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = 'correct_answer'",
            DB_NAME,
            $questions_table
        ));
        
        // Update correct_answer to LONGTEXT if it's VARCHAR(255)
        if ($column_info && strpos(strtolower($column_info->COLUMN_TYPE), 'varchar') !== false) {
            $wpdb->query("ALTER TABLE {$questions_table} MODIFY COLUMN correct_answer LONGTEXT NOT NULL");
        }
        
        // Check if type column is ENUM and needs updating
        $type_info = $wpdb->get_row($wpdb->prepare(
            "SELECT COLUMN_TYPE 
             FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = 'type'",
            DB_NAME,
            $questions_table
        ));
        
        // Update type enum to include advanced question types if needed
        if ($type_info && strpos(strtolower($type_info->COLUMN_TYPE), 'enum') !== false) {
            // Check if new types are already in the enum - check for match_image_label specifically
            $enum_values = strtolower($type_info->COLUMN_TYPE);
            if (strpos($enum_values, 'match_image_label') === false || strpos($enum_values, 'drag_drop_image') === false) {
                // Update enum to include all new question types
                $wpdb->query("ALTER TABLE {$questions_table} MODIFY COLUMN type ENUM(
                    'true_false',
                    'multiple_choice',
                    'short_answer',
                    'fill_blanks',
                    'match_pair',
                    'match_image_label',
                    'drag_drop',
                    'drag_drop_image',
                    'sorting'
                ) NOT NULL");
            }
        }
        
        // Fix questions with empty type by detecting from correct_answer
        $questions_with_empty_type = $wpdb->get_results("SELECT id, correct_answer FROM {$questions_table} WHERE type = '' OR type IS NULL");
        if (!empty($questions_with_empty_type)) {
            foreach ($questions_with_empty_type as $q) {
                $decoded = json_decode($q->correct_answer, true);
                $detected_type = '';
                
                if (is_array($decoded) && !empty($decoded)) {
                    $first_key = key($decoded);
                    // Check if it's match_image_label (associative array with URLs as keys)
                    if (is_string($first_key) && (strpos($first_key, 'http://') === 0 || strpos($first_key, 'https://') === 0 || strpos($first_key, '/') === 0 || strpos(strtoupper($first_key), 'HTTP') === 0)) {
                        $detected_type = 'match_image_label';
                    } elseif (isset($decoded[0]) && is_array($decoded[0]) && isset($decoded[0]['url'])) {
                        $detected_type = 'drag_drop_image';
                    } elseif (is_array($decoded) && !isset($decoded[0]) && !is_numeric($first_key)) {
                        $detected_type = 'match_pair';
                    }
                }
                
                if (!empty($detected_type)) {
                    $wpdb->update($questions_table, array('type' => $detected_type), array('id' => $q->id));
                }
            }
        }

        // Ensure Tutor LMS scheduler table can be created on utf8mb4 (shorter index prefixes)
        $this->ensure_tutor_scheduler_table();

        // Ensure video_quizzes.video_type allows tutor_lms and fix empty types
        $this->ensure_video_quizzes_video_type();
    }

    /**
     * Allow tutor_lms as video_type and repair existing rows.
     */
    private function ensure_video_quizzes_video_type() {
        global $wpdb;
        $table = $wpdb->prefix . 'livq_video_quizzes';

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($table_exists !== $table) {
            return;
        }

        // Check column type
        $type_info = $wpdb->get_row($wpdb->prepare(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'video_type'",
            DB_NAME,
            $table
        ));

        if ($type_info && strpos(strtolower($type_info->COLUMN_TYPE), 'tutor_lms') === false) {
            // Modify enum to include tutor_lms
            $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN video_type ENUM('youtube','vimeo','mp4','tutor_lms') NOT NULL");
        }

        // Repair existing rows where video_type is empty but video_url looks like a Tutor lesson ID (numeric)
        $wpdb->query("UPDATE {$table} SET video_type = 'tutor_lms' WHERE (video_type IS NULL OR video_type = '') AND video_url REGEXP '^[0-9]+$'");
    }

    /**
     * Create Tutor LMS scheduler table with safe index lengths for utf8mb4.
     * This prevents "Specified key was too long; max key length is 1000 bytes" during dbDelta.
     */
    private function ensure_tutor_scheduler_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'tutor_scheduler';

        // If table exists, skip
        $existing = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($existing === $table) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        // Use shorter index prefixes on utf8mb4 to stay under 1000 bytes
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(50) NOT NULL COMMENT 'Type of schedule, e.g., gift, email, reminder',
            reference_id VARCHAR(255) NOT NULL COMMENT 'Unique reference id, token, etc',
            scheduled_at_gmt DATETIME NOT NULL COMMENT 'When the action should be executed',
            status VARCHAR(255) NOT NULL DEFAULT 'processing',
            payload LONGTEXT,
            created_at_gmt DATETIME,
            updated_at_gmt DATETIME,
            scheduled_by BIGINT UNSIGNED COMMENT 'User who scheduled the action',
            scheduled_for BIGINT UNSIGNED COMMENT 'Target user of the scheduled action',
            PRIMARY KEY  (id),
            KEY idx_context_status (type, status(50)),
            KEY idx_status (status(50)),
            KEY idx_scheduled_at_gmt (scheduled_at_gmt)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
