=== Lucrative Interactive VideoQuiz ===
Contributors: yourname
Tags: video, quiz, interactive, education, learning
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make your videos smarter — engage learners with in-video questions. Create interactive video quizzes with pause-and-answer functionality.

== Description ==

Lucrative Interactive VideoQuiz is a powerful WordPress plugin that transforms your videos into engaging, interactive learning experiences. Add questions at specific time points in your videos to create immersive quizzes that pause playback and require user interaction.

= Key Features =

* **Interactive Video Quizzes** - Add questions at specific time points in your videos
* **Multiple Question Types** - True/False and Multiple Choice questions
* **Video Platform Support** - YouTube, Vimeo, and MP4 uploads
* **Modern Dashboard** - Beautiful, tab-based admin interface
* **Responsive Design** - Works perfectly on mobile and desktop
* **Global Settings** - Customize quiz behavior and appearance
* **Shortcode System** - Easy integration anywhere on your site
* **Quiz Analytics** - Track user performance and engagement

= Perfect For =

* Online course creators
* Corporate trainers
* Educational institutions
* YouTube educators
* LMS users (LearnDash, TutorLMS, LifterLMS)
* Content creators

= How It Works =

1. **Create Questions** - Add True/False or Multiple Choice questions
2. **Setup Video** - Upload or link to YouTube/Vimeo videos
3. **Add Time Slots** - Set specific times when questions should appear
4. **Generate Shortcode** - Get a shortcode to embed anywhere
5. **Users Take Quiz** - Video pauses at time slots, users answer questions

= Video Platform Support =

* **YouTube** - Full API integration with pause/play control
* **Vimeo** - Native Vimeo player with time tracking
* **MP4 Uploads** - Direct video file uploads with HTML5 player

= Question Types =

* **True/False** - Simple binary choice questions
* **Multiple Choice** - Up to 4 answer options
* **Explanations** - Add detailed explanations for each question
* **Skip Options** - Allow or prevent question skipping

= Admin Dashboard =

* **Modern Interface** - Clean, tab-based navigation
* **Question Management** - Easy creation and editing
* **Video Setup** - Simple video configuration
* **Global Settings** - Customize quiz behavior
* **Analytics** - View quiz performance and results

= Frontend Experience =

* **Responsive Design** - Works on all devices
* **Smooth Animations** - Professional user experience
* **Progress Tracking** - Visual progress indicators
* **Results Display** - Score and feedback system

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/lucrative-interactive-videoquiz` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Video Quiz menu in your WordPress admin to start creating interactive quizzes
4. Create questions, setup videos, and generate shortcodes to embed on your site

== Frequently Asked Questions ==

= How do I add questions to my videos? =

1. Go to Video Quiz > Questions in your WordPress admin
2. Click "Add New Question" and choose your question type
3. Enter your question text and options
4. Set the correct answer and add an explanation if desired

= How do I create a video quiz? =

1. Go to Video Quiz > Video Quizzes
2. Click "Create Video Quiz"
3. Add your video URL or upload an MP4 file
4. Set time slots for when questions should appear
5. Attach questions to each time slot
6. Copy the generated shortcode to use on your site

= Which video platforms are supported? =

The plugin supports YouTube, Vimeo, and direct MP4 uploads. For YouTube and Vimeo, simply paste the video URL. For MP4 files, upload them through the WordPress media library.

= Can users skip questions? =

Yes, you can configure this in the global settings. You can allow or prevent question skipping based on your requirements.

= Is the plugin mobile-friendly? =

Absolutely! The plugin is fully responsive and works perfectly on mobile devices, tablets, and desktops.

== Screenshots ==

1. Modern dashboard with tab-based navigation
2. Question creation interface
3. Video quiz setup with time slots
4. Interactive quiz overlay on frontend
5. Results display with scoring
6. Global settings configuration

== Changelog ==

= 1.0.1 =
* Security hardening per WordPress.org handbook: strict nonce verification, capability checks (`manage_options`) on admin actions, and thorough sanitization/escaping across admin and AJAX endpoints
* Fixed quiz attempts not recording reliably; ensured results submission on video end and sanitized payload server-side
* Reports tab made resilient when DB tables are missing; auto-creates tables and escapes output
* Increased free limit to 10 questions
* Added Documentation tab with step-by-step guides

= 1.0.0 =
* Initial release
* Question management system
* Video quiz creation
* YouTube, Vimeo, and MP4 support
* Modern admin dashboard
* Responsive frontend design
* Shortcode system
* Global settings
* Quiz analytics

== Upgrade Notice ==

= 1.0.0 =
Initial release of Lucrative Interactive VideoQuiz. Start creating engaging video quizzes today!
