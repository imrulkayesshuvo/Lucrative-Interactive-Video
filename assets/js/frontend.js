/**
 * Lucrative Interactive VideoQuiz - Frontend JavaScript
 * Using Plyr.js for YouTube/Vimeo/MP4 support
 */

jQuery(document).ready(function($) {
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Initialize quiz functionality
    initQuiz();
    
    function initQuiz() {
        console.log('Initializing quiz, found containers:', $('.livq-quiz-container').length);
        
        $('.livq-quiz-container').each(function() {
            var $container = $(this);
            console.log('Processing quiz container:', $container);
            
            var quizData = getQuizData($container);
            console.log('Quiz data:', quizData);
            
            if (quizData) {
                setupQuiz($container, quizData);
            } else {
                console.error('No quiz data found for container');
            }
        });
    }
    
    function getQuizData($container) {
        try {
            var quizData = {
                quiz: {
                    id: $container.data('quiz-id'),
                    video_type: $container.data('video-type'),
                    video_url: $container.data('video-source'),
                    time_slots: $container.data('time-slots') || []
                },
                questions: $container.data('questions') || [],
                settings: {
                    show_correct_answers: $container.attr('data-show-correct') === '1',
                    allow_skipping: $container.attr('data-allow-skip') === '1',
                    completion_message: $container.attr('data-end-message')
                }
            };
            
            console.log('Quiz data from attributes:', quizData);
            console.log('Raw data attributes:');
            console.log('- show-correct:', $container.attr('data-show-correct'));
            console.log('- allow-skip:', $container.attr('data-allow-skip'));
            console.log('- end-message:', $container.attr('data-end-message'));
            console.log('All data attributes on container:');
            console.log($container.data());
            console.log('Container HTML attributes:');
            console.log($container[0].attributes);
            return quizData;
        } catch (e) {
            console.error('Error parsing quiz data from attributes:', e);
            return null;
        }
    }
    
    function setupQuiz($container, quizData) {
        var quiz = quizData.quiz;
        var questions = quizData.questions;
        var settings = quizData.settings;
        
        console.log('Setting up quiz with data:', quizData);
        
        // Create questions lookup - store with both string and integer keys for compatibility
        var questionsLookup = {};
        questions.forEach(function(question) {
            var qId = question.id;
            // Store with both integer and string keys
            questionsLookup[qId] = question;
            questionsLookup[parseInt(qId)] = question;
            questionsLookup[String(qId)] = question;
        });
        
        // Store data in container
        $container.data('questions-lookup', questionsLookup);
        $container.data('quiz-data', quizData);
        $container.data('quiz-settings', settings);
        
        console.log('Questions lookup created:', questionsLookup);
        
        // Setup Plyr video player
        setupPlyrPlayer($container, quiz);
        
        // Setup quiz overlay
        setupQuizOverlay($container, quiz, questionsLookup, settings);
        
        // Setup results overlay
        setupResultsOverlay($container, settings);
    }
    
    function setupPlyrPlayer($container, quiz) {
        var $videoPlayer = $container.find('.livq-video-player');
        
        console.log('Setting up Plyr player, found elements:', $videoPlayer.length);
        console.log('Video player element:', $videoPlayer[0]);
        
        if ($videoPlayer.length === 0) {
            console.error('Video player element not found');
            return;
        }
        
        // Check if Plyr is available
        if (typeof Plyr === 'undefined') {
            console.error('Plyr.js is not loaded, showing fallback video');
            $container.find('.livq-fallback-video').show();
            return;
        }
        
        console.log('Initializing Plyr player...');
        
        try {
            // Initialize Plyr player
            var player = new Plyr($videoPlayer[0], {
                controls: [
                    'play-large',
                    'play',
                    'progress',
                    'current-time',
                    'duration',
                    'mute',
                    'volume',
                    'settings',
                    'fullscreen'
                ],
                settings: ['quality', 'speed'],
                speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2] }
            });
        } catch (error) {
            console.error('Error initializing Plyr player:', error);
            $container.find('.livq-fallback-video').show();
            return;
        }
        
        // Store player reference
        $container.data('plyr-player', player);
        
        // Setup time tracking for quiz
        var timeSlots = quiz.time_slots || [];
        var activeTimeSlots = [];
        var currentTimeSlotIndex = -1;
        var currentQuestionIndex = 0;
        var answeredQuestions = {};
        
        // Store quiz state in container
        $container.data('quiz-state', {
            activeTimeSlots: activeTimeSlots,
            currentTimeSlotIndex: currentTimeSlotIndex,
            currentQuestionIndex: currentQuestionIndex,
            answeredQuestions: answeredQuestions
        });
        
        // Listen for time updates
        player.on('timeupdate', function(event) {
            var currentTime = event.detail.plyr.currentTime;
            console.log('Current time:', currentTime);
            
            // Check for time slots
            timeSlots.forEach(function(slot, index) {
                var slotTime = parseInt(slot.time);
                // Normalize question IDs in slot to integers for matching
                if (slot.questions && Array.isArray(slot.questions)) {
                    slot.questions = slot.questions.map(function(qid) {
                        return parseInt(qid);
                    }).filter(function(qid) {
                        return !isNaN(qid) && qid > 0;
                    });
                }
                if (currentTime >= slotTime && !activeTimeSlots[index]) {
                    console.log('Time slot triggered at', currentTime, 'for slot:', slot);
                    console.log('Slot questions (normalized):', slot.questions);
                    activeTimeSlots[index] = true;
                    currentTimeSlotIndex = index;
                    currentQuestionIndex = 0;
                    
                    // Update quiz state
                    var quizState = $container.data('quiz-state');
                    quizState.activeTimeSlots = activeTimeSlots;
                    quizState.currentTimeSlotIndex = currentTimeSlotIndex;
                    quizState.currentQuestionIndex = currentQuestionIndex;
                    $container.data('quiz-state', quizState);
                    
                    showQuizOverlay($container, slot, quiz);
                }
            });
        });
        
        // Listen for video end
        player.on('ended', function() {
            console.log('Video ended');
            var storedQuizData = $container.data('quiz-data');
            showQuizResults($container, storedQuizData);
        });
        
        // Store functions for quiz overlay
        $container.data('play-video', function() {
            player.play();
        });
        
        $container.data('pause-video', function() {
            player.pause();
        });
        
        console.log('Plyr player initialized:', player);
    }
    
    function setupQuizOverlay($container, quiz, questionsLookup, settings) {
        var $overlay = $container.find('.livq-quiz-overlay');
        var $modal = $overlay.find('.livq-quiz-modal');
        var $questionContainer = $modal.find('.livq-question-container');
        var $submitBtn = $modal.find('.livq-submit-answer');
        var $skipBtn = $modal.find('.livq-skip-question');
        
        // Close quiz overlay
        $modal.find('.livq-close-quiz').on('click', function() {
            hideQuizOverlay($container);
        });
        
        // Submit answer
        $submitBtn.on('click', function() {
            submitAnswer($container, $modal, questionsLookup, settings);
        });
        
        // Skip question
        $skipBtn.on('click', function() {
            skipQuestion($container, $modal, settings);
        });
        
        // Handle option selection
        $questionContainer.on('change', 'input[type="radio"], input[type="checkbox"]', function() {
            updateSubmitButton($modal);
        });
    }
    
    function showQuizOverlay($container, timeSlot, quiz) {
        console.log('showQuizOverlay called with timeSlot:', timeSlot);
        
        var $overlay = $container.find('.livq-quiz-overlay');
        console.log('Found overlay elements:', $overlay.length);
        console.log('Overlay element:', $overlay[0]);
        
        var $questionContainer = $overlay.find('.livq-question-container');
        console.log('Found question container elements:', $questionContainer.length);
        
        var questions = timeSlot.questions || [];
        
        console.log('Questions for this time slot:', questions);
        
        if (questions.length === 0) {
            console.log('No questions for this time slot');
            return;
        }
        
        // Get quiz state
        var quizState = $container.data('quiz-state') || {};
        var currentQuestionIndex = quizState.currentQuestionIndex || 0;
        var answeredQuestions = quizState.answeredQuestions || {};
        
        console.log('Current question index:', currentQuestionIndex);
        console.log('Answered questions:', answeredQuestions);
        
        // Get the questions lookup from the container data
        var questionsLookup = $container.data('questions-lookup') || {};
        console.log('Questions lookup:', questionsLookup);
        
        // Find the next unanswered question
        var questionId = null;
        var questionIndex = -1;
        
        for (var i = currentQuestionIndex; i < questions.length; i++) {
            var qId = questions[i];
            if (!answeredQuestions[qId]) {
                questionId = qId;
                questionIndex = i;
                break;
            }
        }
        
        if (!questionId) {
            console.log('All questions in this time slot have been answered');
            hideQuizOverlay($container);
            resumeVideo($container);
            return;
        }
        
        var question = getQuestionById(questionId, questionsLookup);
        
        if (!question) {
            console.log('Question not found for ID:', questionId);
            return;
        }
        
        console.log('Rendering question:', question);
        console.log('Question index:', questionIndex);
        
        // Reset form state before rendering new question
        resetQuizForm($questionContainer);
        
        // Render question
        renderQuestion($questionContainer, question);
        
        // Show overlay
        console.log('Showing overlay...');
        $overlay.show();
        $overlay.css('display', 'flex'); // Force display
        $overlay.css('z-index', '999999'); // Force high z-index
        console.log('Overlay display style:', $overlay.css('display'));
        console.log('Overlay z-index:', $overlay.css('z-index'));
        
        // Pause video
        var pauseVideo = $container.data('pause-video');
        if (pauseVideo) {
            pauseVideo();
        }
        
        // Store current question data
        $container.data('current-question', question);
        $container.data('current-time-slot', timeSlot);
        $container.data('current-question-index', questionIndex);
        
        // Reset submit button to original state
        var $modal = $overlay.find('.livq-quiz-modal');
        resetSubmitButton($modal);
        
        // Update submit button state
        updateSubmitButton($modal);
    }
    
    function getQuestionById(questionId, questionsLookup) {
        // Get question from the questions lookup - handle both string and integer IDs
        if (questionsLookup) {
            // Try exact match first
            if (questionsLookup[questionId]) {
            return questionsLookup[questionId];
            }
            // Try as integer
            var intId = parseInt(questionId);
            if (questionsLookup[intId]) {
                return questionsLookup[intId];
            }
            // Try as string
            var strId = String(questionId);
            if (questionsLookup[strId]) {
                return questionsLookup[strId];
            }
        }
        
        console.error('Question not found:', questionId);
        return null;
    }
    
    function resetQuizForm($questionContainer) {
        console.log('Resetting quiz form...');
        
        // Remove submitted class
        $questionContainer.removeClass('livq-submitted');
        
        // Clear any existing feedback
        $questionContainer.find('.livq-question-feedback').remove();
        $questionContainer.find('.livq-countdown-timer').remove();
        
        // Re-enable all form elements
        $questionContainer.find('input[type="radio"], input[type="checkbox"]').prop('disabled', false);
        $questionContainer.find('.livq-submit-btn').prop('disabled', false).text('Submit Answer');
        $questionContainer.find('.livq-skip-btn').prop('disabled', false);
        
        // Clear any selected answers
        $questionContainer.find('input[name="answer"]:checked').prop('checked', false);
        
        console.log('Quiz form reset complete');
    }
    
    function resetSubmitButton($modal) {
        console.log('Resetting submit button...');
        
        var $submitBtn = $modal.find('.livq-submit-answer');
        
        // Reset button to original state
        $submitBtn.prop('disabled', true).text('Submit Answer');
        
        // Remove any existing click handlers
        $submitBtn.off('click');
        
        // Re-attach original submit handler
        $submitBtn.on('click', function() {
            var $container = $modal.closest('.livq-quiz-container');
            var questionsLookup = $container.data('questions-lookup') || {};
            var settings = $container.data('quiz-settings') || {};
            submitAnswer($container, $modal, questionsLookup, settings);
        });
        
        console.log('Submit button reset complete');
    }
    
    function renderQuestion($container, question) {
        console.log('Rendering question:', question);
        
        var html = '<div class="livq-question-title">' + question.title + '</div>';
        
        if (question.type === 'true_false') {
            html += '<ul class="livq-question-options">';
            html += '<li><label class="livq-option"><input type="radio" name="answer" value="true"> True</label></li>';
            html += '<li><label class="livq-option"><input type="radio" name="answer" value="false"> False</label></li>';
            html += '</ul>';
        } else if (question.type === 'multiple_choice') {
            html += '<ul class="livq-question-options">';
            question.options.forEach(function(option, index) {
                html += '<li><label class="livq-option"><input type="radio" name="answer" value="' + index + '"> ' + option + '</label></li>';
            });
            html += '</ul>';
        } else if (question.type === 'short_answer') {
            html += '<div class="livq-short-answer-container">';
            html += '<input type="text" class="livq-short-answer-input" name="answer_custom" placeholder="Type your answer here" style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 16px; margin-top: 10px;">';
            html += '</div>';
        } else if (question.type === 'fill_blanks') {
            html += '<div class="livq-fill-blanks-container" data-question-id="' + question.id + '">';
            var text = question.blanks_text || '';
            if (!text && question.options) {
                text = question.options; // Fallback to options field
            }
            var parts = text.split('_____');
            html += '<div class="livq-fill-blanks-text" style="line-height: 2.5; font-size: 16px; margin: 20px 0;">';
            parts.forEach(function(part, index) {
                html += escapeHtml(part);
                if (index < parts.length - 1) {
                    html += '<input type="text" class="livq-blank-input" name="blank_answer_' + index + '" data-index="' + index + '" style="display: inline-block; width: 120px; padding: 5px 10px; margin: 0 5px; border: 1px solid #ddd; border-bottom: 2px solid #667eea; border-radius: 0; background: transparent; text-align: center;">';
                }
            });
            html += '</div></div>';
        } else if (question.type === 'match_pair') {
            html += '<div class="livq-match-pair-container" data-question-id="' + question.id + '">';
            try {
                var pairs = JSON.parse(question.correct_answer);
                var leftItems = [];
                var rightItems = [];
                
                // Extract left and right items from pairs object
                for (var left in pairs) {
                    if (pairs.hasOwnProperty(left)) {
                        leftItems.push(left);
                        rightItems.push(pairs[left]);
                    }
                }
                
                // Shuffle right items
                rightItems.sort(function() { return Math.random() - 0.5; });
                
                html += '<div class="livq-match-column" data-column="left" style="background: #f8f9fa; padding: 20px; border-radius: 8px;">';
                html += '<h4 style="margin-top: 0; margin-bottom: 15px; font-size: 16px; font-weight: 600;">Left Column</h4>';
                leftItems.forEach(function(item, index) {
                    html += '<div class="livq-match-item" data-side="left" data-index="' + index + '" data-value="' + escapeHtml(item) + '" style="background: white; padding: 15px; margin: 10px 0; border-radius: 6px; cursor: pointer; border: 2px solid transparent; transition: all 0.3s;">' + escapeHtml(item) + '</div>';
                });
                html += '</div>';
                
                html += '<div class="livq-match-column" data-column="right" style="background: #f8f9fa; padding: 20px; border-radius: 8px;">';
                html += '<h4 style="margin-top: 0; margin-bottom: 15px; font-size: 16px; font-weight: 600;">Right Column</h4>';
                rightItems.forEach(function(item, index) {
                    html += '<div class="livq-match-item" data-side="right" data-index="' + index + '" data-value="' + escapeHtml(item) + '" style="background: white; padding: 15px; margin: 10px 0; border-radius: 6px; cursor: pointer; border: 2px solid transparent; transition: all 0.3s;">' + escapeHtml(item) + '</div>';
                });
                html += '</div>';
                
                // Store correct pairs for validation
                html += '<input type="hidden" name="answer_custom" value="">';
            } catch(e) {
                console.error('Error parsing match_pair question:', e);
                html += '<p>Error loading match pairs question.</p>';
            }
            html += '</div>';
        } else if (question.type === 'match_image_label') {
            console.log('Rendering match_image_label question:', question);
            html += '<div class="livq-match-image-label-container" data-question-id="' + question.id + '">';
            try {
                // Handle both string and already parsed JSON
                var pairs;
                if (typeof question.correct_answer === 'string') {
                    pairs = JSON.parse(question.correct_answer);
                } else {
                    pairs = question.correct_answer;
                }
                
                console.log('Parsed pairs:', pairs);
                
                if (!pairs || typeof pairs !== 'object' || Object.keys(pairs).length === 0) {
                    console.error('Invalid pairs data:', pairs);
                    html += '<p style="color: red;">Error: Invalid question data. Please check the question configuration.</p>';
                    html += '</div>';
                    $container.empty().html(html);
                    return;
                }
                
                var imageLabels = [];
                var allLabels = [];
                
                // Extract image URLs and labels from pairs object
                for (var imageUrl in pairs) {
                    if (pairs.hasOwnProperty(imageUrl)) {
                        imageLabels.push({
                            url: imageUrl,
                            label: pairs[imageUrl]
                        });
                        allLabels.push(pairs[imageUrl]);
                    }
                }
                
                console.log('Image labels:', imageLabels);
                console.log('All labels:', allLabels);
                
                if (imageLabels.length === 0) {
                    html += '<p style="color: red;">Error: No images found in question data.</p>';
                    html += '</div>';
                    $container.empty().html(html);
                    return;
                }
                
                // Shuffle labels for dragging
                allLabels.sort(function() { return Math.random() - 0.5; });
                
                // Images section with empty label boxes below
                html += '<div class="livq-match-images-section" style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 30px; justify-content: center;">';
                imageLabels.forEach(function(item, index) {
                    html += '<div class="livq-match-image-wrapper" data-image-url="' + escapeHtml(item.url) + '" data-correct-label="' + escapeHtml(item.label) + '" style="display: flex; flex-direction: column; align-items: center; width: 120px;">';
                    // Image box
                    html += '<div class="livq-match-image-box" style="width: 100px; height: 100px; background: #f0f0f0; border: 2px solid #ddd; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; overflow: hidden;">';
                    html += '<img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(item.label) + '" style="max-width: 100%; max-height: 100%; object-fit: cover; pointer-events: none;">';
                    html += '</div>';
                    // Label drop box below image
                    html += '<div class="livq-match-label-box" data-image-index="' + index + '" style="width: 100px; min-height: 50px; background: white; border: 2px dashed #adb5bd; border-radius: 6px; display: flex; align-items: center; justify-content: center; padding: 8px; cursor: pointer; transition: all 0.3s; pointer-events: auto !important; position: relative;" draggable="false">';
                    html += '<span class="livq-label-placeholder" style="color: #6c757d; font-size: 12px; text-align: center;">Drop label here</span>';
                    html += '</div>';
                    html += '</div>';
                });
                html += '</div>';
                
                // Draggable labels section
                html += '<div class="livq-match-labels-section" style="margin-top: 20px;">';
                html += '<h4 style="margin-bottom: 15px; font-size: 16px; font-weight: 600; text-align: center;">Drag labels to match with images:</h4>';
                html += '<div class="livq-match-labels-list" style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">';
                allLabels.forEach(function(label) {
                    html += '<div class="livq-match-label-item" draggable="true" data-label="' + escapeHtml(label) + '" style="background: white; padding: 12px 20px; border-radius: 6px; cursor: move; border: 2px solid #667eea; color: #667eea; font-weight: 500; user-select: none; transition: all 0.3s;">' + escapeHtml(label) + '</div>';
                });
                html += '</div></div>';
                
                // Store correct pairs for validation
                html += '<input type="hidden" name="answer_custom" value="">';
            } catch(e) {
                console.error('Error parsing match_image_label question:', e);
                console.error('Question data:', question);
                html += '<p style="color: red;">Error loading match image to label question: ' + e.message + '</p>';
                html += '<pre style="font-size: 10px; overflow: auto;">' + escapeHtml(JSON.stringify(question, null, 2)) + '</pre>';
            }
            html += '</div>';
        } else if (question.type === 'drag_drop') {
            html += '<div class="livq-drag-drop-container" data-question-id="' + question.id + '">';
            html += '<ul class="livq-drag-list">';
            // Items should be shuffled
            var items = JSON.parse(question.correct_answer);
             // Simple shuffle
            items.sort(() => Math.random() - 0.5);
            
            items.forEach(function(item) {
                html += '<li class="livq-drag-item" draggable="true" data-value="' + item + '">' + item + '</li>';
            });
            html += '</ul></div>';
        } else if (question.type === 'drag_drop_image') {
            html += '<div class="livq-drag-drop-container livq-drag-images" data-question-id="' + question.id + '">';
            
            // Source area - shuffled images
            html += '<div class="livq-drag-source-area">';
            html += '<h4>Drag images from here:</h4>';
            html += '<ul class="livq-drag-source-list horizontal" style="display: flex !important; flex-direction: row !important; flex-wrap: wrap !important; gap: 8px !important; list-style: none !important; padding: 0 !important; margin: 0 !important; overflow: visible !important; width: 100% !important;">';
            
            // Parse fresh from question data to avoid caching issues
            var items = JSON.parse(JSON.stringify(JSON.parse(question.correct_answer)));
            
            // Shuffle for source - use proper Fisher-Yates shuffle with multiple random sources
            // This ensures different order every time, even on page reload
            var shuffledItems = items.slice();
            for (var i = shuffledItems.length - 1; i > 0; i--) {
                // Use multiple random sources for better randomization
                var random1 = Math.random();
                var random2 = Math.random();
                var random3 = Date.now() % 1000 / 1000;
                var combinedRandom = (random1 + random2 + random3) / 3;
                var j = Math.floor(combinedRandom * (i + 1));
                var temp = shuffledItems[i];
                shuffledItems[i] = shuffledItems[j];
                shuffledItems[j] = temp;
            }
            console.log('Original order:', items.map(function(item) { return item.id; }));
            console.log('Shuffled order:', shuffledItems.map(function(item) { return item.id; }));
            
            shuffledItems.forEach(function(item) {
                html += '<li class="livq-drag-source-item image-item" draggable="true" data-id="' + item.id + '" data-url="' + item.url + '" data-label="' + (item.label || '') + '" style="width: 80px !important; min-width: 80px !important; max-width: 80px !important; height: 100px !important; flex-shrink: 0 !important; display: flex !important; flex-direction: column !important; align-items: center !important; cursor: move !important; background: white !important; border: 2px solid #667eea !important; border-radius: 6px !important; padding: 6px !important; box-sizing: border-box !important;">';
                html += '<img src="' + item.url + '" alt="' + (item.label || 'Image') + '" draggable="false" style="width: 100% !important; height: 60px !important; object-fit: cover !important; display: block !important; border-radius: 4px !important; margin-bottom: 4px !important; pointer-events: none !important;">';
                if (item.label) html += '<span class="caption" style="font-size: 11px; line-height: 1.2; word-break: break-word; color: #666;">' + item.label + '</span>';
                html += '</li>';
            });
            html += '</ul></div>';
            
            // Answer boxes area - empty slots
            html += '<div class="livq-drag-answer-area">';
            html += '<h4>Drop images here in correct order:</h4>';
            html += '<ul class="livq-drag-answer-list horizontal" style="display: flex !important; flex-direction: row !important; flex-wrap: wrap !important; gap: 8px !important; list-style: none !important; padding: 0 !important; margin: 0 !important; overflow: visible !important; width: 100% !important;">';
            items.forEach(function(item, index) {
                html += '<li class="livq-drag-answer-box" data-position="' + index + '" data-expected-id="' + item.id + '" style="width: 80px !important; min-width: 80px !important; max-width: 80px !important; min-height: 100px !important; flex-shrink: 0 !important; display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; border: 2px dashed #adb5bd !important; border-radius: 6px !important; background: #f8f9fa !important; padding: 6px !important; box-sizing: border-box !important; pointer-events: auto !important; user-select: none !important;">';
                html += '<div class="answer-box-placeholder" style="text-align: center; width: 100%; pointer-events: none !important; user-select: none !important;">';
                html += '<span class="box-number" style="display: block; font-size: 18px; font-weight: bold; color: #667eea; margin-bottom: 5px; pointer-events: none !important;">' + (index + 1) + '</span>';
                html += '<span class="box-label" style="display: block; font-size: 10px; color: #6c757d; pointer-events: none !important;">Drop image here</span>';
                html += '</div>';
                html += '</li>';
            });
            html += '</ul></div>';
            html += '</div>';
        } else if (question.type === 'sorting') {
            html += '<div class="livq-drag-drop-container livq-sorting" data-question-id="' + question.id + '">';
            html += '<ul class="livq-drag-list">';
            var items = JSON.parse(question.correct_answer);
            // Shuffle
            items.sort(() => Math.random() - 0.5);
            
            items.forEach(function(item) {
                html += '<li class="livq-drag-item" draggable="true" data-value="' + item + '">' + item + '</li>';
            });
            html += '</ul></div>';
        }
        
        // Clear container and add fresh HTML
        $container.empty().html(html);
        
        // Initialize drag and drop handlers if needed - use setTimeout to ensure DOM is ready
        if (question.type === 'drag_drop' || question.type === 'drag_drop_image' || question.type === 'sorting') {
            setTimeout(function() {
            initDragAndDrop($container);
            }, 100);
        }
        
        // Initialize match pair handlers
        if (question.type === 'match_pair') {
            setTimeout(function() {
                initMatchPair($container);
            }, 100);
        }
        
        // Initialize match image label handlers
        if (question.type === 'match_image_label') {
            setTimeout(function() {
                initMatchImageLabel($container);
            }, 100);
        }
        
        // Initialize short answer handlers
        if (question.type === 'short_answer') {
            setTimeout(function() {
                initShortAnswer($container);
            }, 100);
        }
        
        // Initialize fill blanks handlers
        if (question.type === 'fill_blanks') {
            setTimeout(function() {
                initFillBlanks($container);
            }, 100);
        }
        
        console.log('Question rendered successfully');
    }
    
    function initMatchPair($container) {
        var selectedLeft = null;
        var selectedRight = null;
        var matchedPairs = {};
        
        // Reset selections
        $container.find('.livq-match-item').off('click').on('click', function() {
            if ($(this).hasClass('matched')) {
                return; // Already matched
            }
            
            var side = $(this).data('side');
            var value = $(this).data('value');
            
            if (side === 'left') {
                // Deselect previous left selection
                $container.find('.livq-match-item[data-side="left"]').removeClass('selected');
                $(this).addClass('selected');
                selectedLeft = value;
                selectedRight = null; // Reset right selection
            } else if (side === 'right') {
                // Deselect previous right selection
                $container.find('.livq-match-item[data-side="right"]').removeClass('selected');
                $(this).addClass('selected');
                selectedRight = value;
            }
            
            // Check if both are selected
            if (selectedLeft && selectedRight) {
                matchedPairs[selectedLeft] = selectedRight;
                
                // Mark as matched
                $container.find('.livq-match-item[data-value="' + selectedLeft + '"]').addClass('matched').removeClass('selected');
                $container.find('.livq-match-item[data-value="' + selectedRight + '"]').addClass('matched').removeClass('selected');
                
                // Store matches for submission
                var answerJson = JSON.stringify(matchedPairs);
                var $input = $container.find('input[name="answer_custom"]');
                if ($input.length === 0) {
                    $container.append('<input type="hidden" name="answer_custom" value="">');
                    $input = $container.find('input[name="answer_custom"]');
                }
                $input.val(answerJson);
                
                // Update submit button
                var $modal = $container.closest('.livq-quiz-overlay');
                if ($modal.length === 0) {
                    $modal = $container.closest('.livq-quiz-modal');
                }
                if ($modal.length > 0) {
                    updateSubmitButton($modal);
                }
                
                selectedLeft = null;
                selectedRight = null;
            }
        });
    }
    
    function initMatchImageLabel($container) {
        var draggedLabel = null;
        var matchedPairs = {};
        
        // Drag start - label item
        $container.find('.livq-match-label-item').off('dragstart').on('dragstart', function(e) {
            draggedLabel = $(this).data('label');
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/html', $(this).html());
        });
        
        // Drag end
        $container.find('.livq-match-label-item').off('dragend').on('dragend', function() {
            $(this).removeClass('dragging');
            draggedLabel = null;
        });
        
        // Drag over - label box
        $container.find('.livq-match-label-box').off('dragover').on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!$(this).hasClass('filled')) {
                $(this).addClass('drag-over');
                e.originalEvent.dataTransfer.dropEffect = 'move';
            }
        });
        
        // Drag leave
        $container.find('.livq-match-label-box').off('dragleave').on('dragleave', function() {
            $(this).removeClass('drag-over');
        });
        
        // Drop - label box
        $container.find('.livq-match-label-box').off('drop').on('drop', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            $(this).removeClass('drag-over');
            
            if (!draggedLabel) return;
            
            // Remove label from source if it exists
            var $labelItem = $container.find('.livq-match-label-item[data-label="' + draggedLabel + '"]');
            if ($labelItem.length > 0) {
                $labelItem.fadeOut(200, function() {
                    $(this).remove();
                });
            }
            
            // Get image URL and correct label
            var $wrapper = $(this).closest('.livq-match-image-wrapper');
            var imageUrl = $wrapper.data('image-url');
            var correctLabel = $wrapper.data('correct-label');
            
            // Fill the box
            $(this).addClass('filled').html('<span style="color: #333; font-weight: 500;">' + escapeHtml(draggedLabel) + '</span>');
            $(this).find('.livq-label-placeholder').remove();
            
            // Store match
            matchedPairs[imageUrl] = draggedLabel;
            
            // Update hidden input
            var answerJson = JSON.stringify(matchedPairs);
            var $input = $container.find('input[name="answer_custom"]');
            if ($input.length === 0) {
                $container.append('<input type="hidden" name="answer_custom" value="">');
                $input = $container.find('input[name="answer_custom"]');
            }
            $input.val(answerJson);
            
            // Update submit button
            var $modal = $container.closest('.livq-quiz-overlay');
            if ($modal.length === 0) {
                $modal = $container.closest('.livq-quiz-modal');
            }
            if ($modal.length > 0) {
                updateSubmitButton($modal);
            }
            
            draggedLabel = null;
        });
        
        // Double-click to remove label from box
        $container.find('.livq-match-label-box').off('dblclick').on('dblclick', function() {
            if ($(this).hasClass('filled')) {
                var labelText = $(this).text().trim();
                var $wrapper = $(this).closest('.livq-match-image-wrapper');
                var imageUrl = $wrapper.data('image-url');
                
                // Remove from matched pairs
                if (matchedPairs[imageUrl]) {
                    delete matchedPairs[imageUrl];
                }
                
                // Restore label item
                var $labelsList = $container.find('.livq-match-labels-list');
                var $labelItem = $('<div class="livq-match-label-item" draggable="true" data-label="' + escapeHtml(labelText) + '" style="background: white; padding: 12px 20px; border-radius: 6px; cursor: move; border: 2px solid #667eea; color: #667eea; font-weight: 500; user-select: none; transition: all 0.3s;">' + escapeHtml(labelText) + '</div>');
                $labelsList.append($labelItem);
                
                // Re-initialize drag handlers for new item
                $labelItem.on('dragstart', function(e) {
                    draggedLabel = $(this).data('label');
                    $(this).addClass('dragging');
                    e.originalEvent.dataTransfer.effectAllowed = 'move';
                    e.originalEvent.dataTransfer.setData('text/html', $(this).html());
                });
                $labelItem.on('dragend', function() {
                    $(this).removeClass('dragging');
                    draggedLabel = null;
                });
                
                // Clear box
                $(this).removeClass('filled').html('<span class="livq-label-placeholder" style="color: #6c757d; font-size: 12px; text-align: center;">Drop label here</span>');
                
                // Update hidden input
                var answerJson = JSON.stringify(matchedPairs);
                var $input = $container.find('input[name="answer_custom"]');
                if ($input.length === 0) {
                    $container.append('<input type="hidden" name="answer_custom" value="">');
                    $input = $container.find('input[name="answer_custom"]');
                }
                $input.val(answerJson);
                
                // Update submit button
                var $modal = $container.closest('.livq-quiz-overlay');
                if ($modal.length === 0) {
                    $modal = $container.closest('.livq-quiz-modal');
                }
                if ($modal.length > 0) {
                    updateSubmitButton($modal);
                }
            }
        });
    }
    
    function initShortAnswer($container) {
        $container.find('.livq-short-answer-input').off('input').on('input', function() {
            var answer = $(this).val();
            var $input = $container.find('input[name="answer_custom"]');
            if ($input.length === 0) {
                $container.append('<input type="hidden" name="answer_custom" value="">');
                $input = $container.find('input[name="answer_custom"]');
            }
            $input.val(answer);
            
            // Update submit button
            var $modal = $container.closest('.livq-quiz-overlay');
            if ($modal.length === 0) {
                $modal = $container.closest('.livq-quiz-modal');
            }
            if ($modal.length > 0) {
                updateSubmitButton($modal);
            }
        });
    }
    
    function initFillBlanks($container) {
        $container.find('.livq-blank-input').off('input').on('input', function() {
            var blanks = [];
            $container.find('.livq-blank-input').each(function() {
                blanks.push($(this).val() || '');
            });
            
            var answerJson = JSON.stringify(blanks);
            var $input = $container.find('input[name="answer_custom"]');
            if ($input.length === 0) {
                $container.append('<input type="hidden" name="answer_custom" value="">');
                $input = $container.find('input[name="answer_custom"]');
            }
            $input.val(answerJson);
            
            // Update submit button
            var $modal = $container.closest('.livq-quiz-overlay');
            if ($modal.length === 0) {
                $modal = $container.closest('.livq-quiz-modal');
            }
            if ($modal.length > 0) {
                updateSubmitButton($modal);
            }
        });
    }
    
    function initDragAndDrop($container) {
        var draggedItem = null;
        var draggedData = null;
        
        // Check if this is drag-drop-image type with source/answer structure
        // The livq-drag-images class is on the inner div, so check for it inside the container
        var $dragContainer = $container.find('.livq-drag-images').length > 0 ? $container.find('.livq-drag-images') : $container;
        var isDragDropImage = $container.hasClass('livq-drag-images') || $container.find('.livq-drag-images').length > 0;
        
        console.log('Initializing drag and drop for container:', $container);
        console.log('Is drag-drop-image:', isDragDropImage);
        console.log('Drag container:', $dragContainer);
        
        if (isDragDropImage) {
            // Use the inner container if it exists
            var $targetContainer = $dragContainer.length > 0 && !$dragContainer.is($container) ? $dragContainer : $container;
            console.log('Using target container:', $targetContainer);
            
            // Use event delegation for source items to handle dynamically added elements
            $targetContainer.off('dragstart', '.livq-drag-source-item').on('dragstart', '.livq-drag-source-item', function(e) {
                draggedItem = this;
                var $item = $(this);
                draggedData = {
                    id: $item.data('id'),
                    url: $item.data('url'),
                    label: $item.data('label'),
                    element: $item
                };
                console.log('Drag started:', draggedData);
                if (e.originalEvent && e.originalEvent.dataTransfer) {
                    e.originalEvent.dataTransfer.effectAllowed = 'move';
                    e.originalEvent.dataTransfer.dropEffect = 'move';
                    // Set data in a way that works across browsers
                    e.originalEvent.dataTransfer.setData('text/plain', String($item.data('id')));
                    e.originalEvent.dataTransfer.setData('application/json', JSON.stringify(draggedData));
                }
                $item.addClass('dragging');
                // Allow drop on all answer boxes
                setTimeout(function() {
                    $targetContainer.find('.livq-drag-answer-box').each(function() {
                        this.setAttribute('data-droppable', 'true');
                    });
                }, 10);
            });
            
            // Handle answer boxes (drop zones) - use event delegation
            $targetContainer.off('dragover dragenter dragleave drop', '.livq-drag-answer-box');
            
            $targetContainer.on('dragover', '.livq-drag-answer-box', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (e.originalEvent && e.originalEvent.dataTransfer) {
                    e.originalEvent.dataTransfer.dropEffect = 'move';
                    e.originalEvent.dataTransfer.effectAllowed = 'move';
                }
                if (!$(this).hasClass('filled')) {
                    $(this).addClass('drag-over');
                }
                return false;
            });
            
            $targetContainer.on('dragenter', '.livq-drag-answer-box', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (e.originalEvent && e.originalEvent.dataTransfer) {
                    e.originalEvent.dataTransfer.dropEffect = 'move';
                }
                if (!$(this).hasClass('filled')) {
                    $(this).addClass('drag-over');
                }
                return false;
            });
            
            $targetContainer.on('dragleave', '.livq-drag-answer-box', function(e) {
                // Only remove drag-over if we're actually leaving the element
                if (!$(this).is(e.relatedTarget) && !$(this).has(e.relatedTarget).length) {
                    $(this).removeClass('drag-over');
                }
            });
            
            $targetContainer.on('drop', '.livq-drag-answer-box', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                console.log('Drop event triggered', draggedData, draggedItem);
                
                if (!draggedData || !draggedItem) {
                    console.log('No dragged data available, trying to get from dataTransfer');
                    // Try to get data from dataTransfer as fallback
                    if (e.originalEvent && e.originalEvent.dataTransfer) {
                        var dataText = e.originalEvent.dataTransfer.getData('text/plain');
                        var dataJson = e.originalEvent.dataTransfer.getData('application/json');
                        if (dataJson) {
                            try {
                                draggedData = JSON.parse(dataJson);
                                draggedItem = $targetContainer.find('.livq-drag-source-item[data-id="' + draggedData.id + '"]')[0];
                                console.log('Recovered dragged data from dataTransfer:', draggedData);
                            } catch(err) {
                                console.log('Failed to parse dragged data:', err);
                                return false;
                            }
                        } else if (dataText) {
                            draggedItem = $targetContainer.find('.livq-drag-source-item[data-id="' + dataText + '"]')[0];
                            if (draggedItem) {
                                var $item = $(draggedItem);
                                draggedData = {
                                    id: $item.data('id'),
                                    url: $item.data('url'),
                                    label: $item.data('label'),
                                    element: $item
                                };
                                console.log('Recovered dragged data from ID:', draggedData);
                            } else {
                                console.log('Could not find dragged item');
                                return false;
                            }
                        } else {
                            console.log('No dragged data available');
                            return false;
                        }
                    } else {
                        return false;
                    }
                }
                
                console.log('Processing drop with data:', draggedData);
                
                var $answerBox = $(this);
                
                // Remove drag-over class
                $answerBox.removeClass('drag-over');
                
                // If box already has an image, return it to source first
                if ($answerBox.hasClass('filled')) {
                    var existingImage = $answerBox.find('.livq-drag-source-item');
                    if (existingImage.length) {
                        $targetContainer.find('.livq-drag-source-list').append(existingImage);
                        existingImage.removeClass('in-answer');
                    }
                }
                
                // Create new image element from dragged data
                var $newImage = $(draggedItem).clone(true, true); // Clone with data and events
                $newImage.removeClass('dragging').addClass('in-answer');
                $newImage.attr('draggable', 'false'); // Don't allow dragging from answer box
                
                // Ensure proper sizing and styling - match source item dimensions
                $newImage.css({
                    'width': '100%',
                    'max-width': '100%',
                    'height': 'auto',
                    'padding': '0',
                    'margin': '0',
                    'border': 'none',
                    'background': 'transparent',
                    'display': 'flex',
                    'flex-direction': 'column',
                    'align-items': 'center',
                    'cursor': 'default'
                });
                
                // Ensure image sizing is correct
                $newImage.find('img').css({
                    'width': '100%',
                    'max-width': '100%',
                    'height': '60px',
                    'min-height': '60px',
                    'max-height': '60px',
                    'object-fit': 'cover',
                    'display': 'block',
                    'border-radius': '4px',
                    'margin-bottom': '4px'
                });
                
                // Remove placeholder and add image
                $answerBox.find('.answer-box-placeholder').remove();
                $answerBox.append($newImage);
                $answerBox.addClass('filled');
                
                // Remove from source (only if it still exists)
                if ($(draggedItem).length > 0) {
                    $(draggedItem).remove();
                }
                
                // Clear dragged data
                draggedItem = null;
                draggedData = null;
                
                // Update answer
                updateDragDropImageAnswer($targetContainer);
                
                // Update submit button - find the modal
                var $modal = $targetContainer.closest('.livq-quiz-overlay');
                if ($modal.length === 0) {
                    $modal = $targetContainer.closest('.livq-quiz-modal');
                }
                if ($modal.length > 0) {
                    updateSubmitButton($modal);
                }
            });
            
            // Allow removing images from answer boxes (double-click or button)
            $targetContainer.on('dblclick', '.livq-drag-answer-box.filled .livq-drag-source-item', function(e) {
                e.stopPropagation();
                var $answerBox = $(this).closest('.livq-drag-answer-box');
                var $image = $(this);
                
                // Return to source
                $targetContainer.find('.livq-drag-source-list').append($image);
                $image.removeClass('in-answer');
                
                // Restore placeholder
                var position = $answerBox.data('position');
                $answerBox.removeClass('filled').html(
                    '<div class="answer-box-placeholder">' +
                    '<span class="box-number">' + (position + 1) + '</span>' +
                    '<span class="box-label">Drop image here</span>' +
                    '</div>'
                );
                
                updateDragDropImageAnswer($targetContainer);
                
                // Update submit button - find the modal
                var $modal = $targetContainer.closest('.livq-quiz-overlay');
                if ($modal.length === 0) {
                    $modal = $targetContainer.closest('.livq-quiz-modal');
                }
                if ($modal.length > 0) {
                    updateSubmitButton($modal);
                }
            });
            
            $targetContainer.on('dragend', '.livq-drag-source-item', function(e) {
                $(this).removeClass('dragging');
                $targetContainer.find('.livq-drag-answer-box').removeClass('drag-over');
                // Reset dragged data after a short delay
                setTimeout(function() {
                    if (!draggedData || $(draggedItem).length === 0) {
                        draggedItem = null;
                        draggedData = null;
                    }
                }, 100);
            });
            
        } else {
            // Original drag-drop logic for other types (drag_drop, sorting)
        $container.find('.livq-drag-item').on('dragstart', function(e) {
            draggedItem = this;
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            $(this).addClass('dragging');
        });
        
        $container.find('.livq-drag-item').on('dragover', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
            return false;
        });
        
        $container.find('.livq-drag-item').on('dragenter', function(e) {
            $(this).addClass('drag-over');
        });
        
        $container.find('.livq-drag-item').on('dragleave', function(e) {
            $(this).removeClass('drag-over');
        });
        
        $container.find('.livq-drag-item').on('drop', function(e) {
            e.stopPropagation();
            
            if (draggedItem !== this) {
                var $dragged = $(draggedItem);
                var $dropped = $(this);
                    var draggedIndex = $dragged.index();
                    var droppedIndex = $dropped.index();
                    
                    if (draggedIndex < droppedIndex) {
                        $dropped.after($dragged);
                    } else {
                        $dropped.before($dragged);
                    }
            }
            return false;
        });
        
        $container.find('.livq-drag-item').on('dragend', function(e) {
            $(this).removeClass('dragging');
            $container.find('.livq-drag-item').removeClass('drag-over');
            updateDragDropAnswer($container);
        });
        }
    }
    
    function updateDragDropImageAnswer($container) {
        var items = [];
        // Get images in order from answer boxes
        $container.find('.livq-drag-answer-box').each(function() {
            var $box = $(this);
            if ($box.hasClass('filled')) {
                var $image = $box.find('.livq-drag-source-item');
                if ($image.length) {
                    items.push({
                        id: $image.data('id'),
                        url: $image.data('url'),
                        label: $image.data('label') || ''
                    });
                } else {
                    items.push(null); // Empty slot
                }
            } else {
                items.push(null); // Empty slot
            }
        });
        
        // Store answer
        var answerJson = JSON.stringify(items);
        var $input = $container.find('input[name="answer_custom"]');
        if ($input.length === 0) {
            $container.append('<input type="hidden" name="answer_custom" value="">');
            $input = $container.find('input[name="answer_custom"]');
        }
        $input.val(answerJson);
        
        // Trigger change to update submit button
        $container.trigger('change');
    }
    
    function updateDragDropAnswer($container) {
        var items = [];
        $container.find('.livq-drag-item').each(function() {
            if ($(this).hasClass('image-item')) {
                 items.push({
                     id: $(this).data('id'),
                     url: $(this).find('img').attr('src'),
                     label: $(this).find('.caption').text()
                 });
            } else {
                items.push($(this).data('value'));
            }
        });
        // We need to store this answer somehow to be picked up by submitAnswer
        // Since submitAnswer looks for input[name="answer"]:checked, we might need a hidden input
        // Or modify submitAnswer to look for data attribute
        
        // Let's add a hidden input
        var answerJson = JSON.stringify(items);
        var $input = $container.find('input[name="answer_custom"]');
        if ($input.length === 0) {
            $container.append('<input type="hidden" name="answer_custom" value="">');
            $input = $container.find('input[name="answer_custom"]');
        }
        $input.val(answerJson);
        
        // Trigger change to update submit button
        $container.trigger('change');
    }
    
    function updateSubmitButton($modal) {
        var $submitBtn = $modal.find('.livq-submit-answer');
        var hasAnswer = $modal.find('input[name="answer"]:checked').length > 0;
        
        // Check for custom answer input
        if (!hasAnswer) {
            var customAnswer = $modal.find('input[name="answer_custom"]').val();
            if (customAnswer && customAnswer.length > 0) {
                hasAnswer = true;
            }
        }
        
        // Check for short answer
        if (!hasAnswer) {
            var shortAnswer = $modal.find('.livq-short-answer-input').val();
            if (shortAnswer && shortAnswer.length > 0) {
                hasAnswer = true;
            }
        }
        
        // Check for fill blanks
        if (!hasAnswer && $modal.find('.livq-fill-blanks-container').length > 0) {
            var allFilled = true;
            var hasBlanks = false;
            $modal.find('.livq-blank-input').each(function() {
                hasBlanks = true;
                if (!$(this).val() || $(this).val().trim() === '') {
                    allFilled = false;
                }
            });
            hasAnswer = hasBlanks && allFilled;
        }
        
        // Check for match_pair
        if (!hasAnswer && $modal.find('.livq-match-pair-container').length > 0) {
            var leftItems = $modal.find('.livq-match-item[data-side="left"]').length;
            var matchedLeftItems = $modal.find('.livq-match-item[data-side="left"].matched').length;
            hasAnswer = leftItems > 0 && matchedLeftItems === leftItems;
        }
        
        // Check for match_image_label - all label boxes must be filled
        if (!hasAnswer && $modal.find('.livq-match-image-label-container').length > 0) {
            var totalBoxes = $modal.find('.livq-match-label-box').length;
            var filledBoxes = $modal.find('.livq-match-label-box.filled').length;
            hasAnswer = totalBoxes > 0 && filledBoxes === totalBoxes;
        }
        
        // Check for drag-drop-image - all answer boxes must be filled
        if (!hasAnswer) {
            var $dragImages = $modal.find('.livq-drag-images');
            if ($dragImages.length > 0) {
                var allBoxesFilled = true;
                var totalBoxes = $dragImages.find('.livq-drag-answer-box').length;
                $dragImages.find('.livq-drag-answer-box').each(function() {
                    if (!$(this).hasClass('filled')) {
                        allBoxesFilled = false;
                    }
                });
                hasAnswer = allBoxesFilled && totalBoxes > 0;
                console.log('Drag-drop-image check - allBoxesFilled:', allBoxesFilled, 'totalBoxes:', totalBoxes);
            }
        }
        
        console.log('Update submit button - hasAnswer:', hasAnswer);
        $submitBtn.prop('disabled', !hasAnswer);
    }
    
    function submitAnswer($container, $modal, questionsLookup, settings) {
        var $questionContainer = $modal.find('.livq-question-container');
        var selectedAnswer = $questionContainer.find('input[name="answer"]:checked').val();
        
        // Check for custom answer
        if (!selectedAnswer) {
            selectedAnswer = $questionContainer.find('input[name="answer_custom"]').val();
            if (selectedAnswer) {
                // Parse if it looks like JSON
                try {
                    selectedAnswer = JSON.parse(selectedAnswer);
                } catch(e) {}
            }
        }
        
        // Check for short answer
        if (!selectedAnswer) {
            var shortVal = $questionContainer.find('.livq-short-answer-input').val();
            if (shortVal) selectedAnswer = shortVal;
        }
        
        // Check for fill blanks
        if (!selectedAnswer && $questionContainer.find('.livq-fill-blanks-container').length > 0) {
            var blanks = [];
            $questionContainer.find('.livq-blank-input').each(function() {
                blanks.push($(this).val() || '');
            });
            selectedAnswer = blanks;
        }
        
        // Check for match_pair
        if (!selectedAnswer && $questionContainer.find('.livq-match-pair-container').length > 0) {
            var matchAnswer = $questionContainer.find('input[name="answer_custom"]').val();
            if (matchAnswer) {
                try {
                    selectedAnswer = JSON.parse(matchAnswer);
                } catch(e) {
                    selectedAnswer = matchAnswer;
                }
            }
        }
        
        // Check for match_image_label
        if (!selectedAnswer && $questionContainer.find('.livq-match-image-label-container').length > 0) {
            var matchImageAnswer = $questionContainer.find('input[name="answer_custom"]').val();
            if (matchImageAnswer) {
                try {
                    selectedAnswer = JSON.parse(matchImageAnswer);
                } catch(e) {
                    selectedAnswer = matchImageAnswer;
                }
            }
        }

        var currentQuestion = $container.data('current-question');
        
        if (!selectedAnswer) {
            return;
        }
        
        // ... (rest of submitAnswer logic remains similar, just ensure checkAnswer handles objects/arrays)
        
        // Check if already submitted to prevent double submission
        if ($questionContainer.hasClass('livq-submitted')) {
            console.log('Answer already submitted, ignoring duplicate submission');
            return;
        }
        
        // Mark as submitted to prevent double submission
        $questionContainer.addClass('livq-submitted');
        
        // Disable all form elements
        $questionContainer.find('input, button, .livq-drag-item').prop('disabled', true).attr('draggable', false);
        $questionContainer.find('.livq-submit-btn').prop('disabled', true).text('Submitted');
        $questionContainer.find('.livq-skip-btn').prop('disabled', true);
        
        
        // Check if answer is correct
        var isCorrect = checkAnswer(currentQuestion, selectedAnswer);
        
        // Show visual feedback for drag-drop-image questions
        if (currentQuestion.type === 'drag_drop_image') {
            showDragDropImageFeedback($questionContainer, currentQuestion, selectedAnswer, isCorrect);
        }
        
        // Show visual feedback for match-image-label questions
        if (currentQuestion.type === 'match_image_label') {
            showMatchImageLabelFeedback($questionContainer, currentQuestion, selectedAnswer, isCorrect);
        }
        
        // Always show feedback with correct answer
        showQuestionFeedback($questionContainer, currentQuestion, selectedAnswer, isCorrect);
        
        // Store answer
        storeAnswer($container, currentQuestion.id, selectedAnswer, isCorrect);
        
        // Update quiz state - mark this question as answered
        var quizState = $container.data('quiz-state') || {};
        quizState.answeredQuestions = quizState.answeredQuestions || {};
        quizState.answeredQuestions[currentQuestion.id] = true;
        $container.data('quiz-state', quizState);
        
        console.log('Question answered:', currentQuestion.id);
        
        // ... (rest of progression logic)
        // Find next unanswered question in this time slot
        var currentTimeSlot = $container.data('current-time-slot');
        var currentQuestionIndex = $container.data('current-question-index') || 0;
        var questions = currentTimeSlot.questions || [];
        
        var nextQuestionId = null;
        for (var i = currentQuestionIndex + 1; i < questions.length; i++) {
            var qId = questions[i];
            if (!quizState.answeredQuestions[qId]) {
                nextQuestionId = qId;
                break;
            }
        }
        
        if (nextQuestionId) {
            // ... (next question logic)
             setTimeout(function() {
                var $submitBtn = $modal.find('.livq-submit-answer');
                $submitBtn.text('Continue to Next Question');
                $submitBtn.prop('disabled', false); // Re-enable for click
                $submitBtn.off('click').on('click', function() {
                    showQuizOverlay($container, currentTimeSlot, $container.data('quiz-data').quiz);
                });
            }, 1000);
             setTimeout(function() {
                showQuizOverlay($container, currentTimeSlot, $container.data('quiz-data').quiz);
            }, 5000);
        } else {
            // ... (resume video logic)
             setTimeout(function() {
                var $submitBtn = $modal.find('.livq-submit-answer');
                $submitBtn.text('Continue Video');
                $submitBtn.prop('disabled', false);
                $submitBtn.off('click').on('click', function() {
                    hideQuizOverlay($container);
                    resumeVideo($container);
                });
            }, 1000);
            
            var delayTime = isCorrect ? 30000 : 25000;
            setTimeout(function() {
                hideQuizOverlay($container);
                resumeVideo($container);
            }, delayTime);
        }
    }
    
    function showMatchImageLabelFeedback($container, question, userAnswer, isCorrect) {
        var correct = JSON.parse(question.correct_answer);
        
        // Mark each label box as correct or incorrect
        $container.find('.livq-match-image-wrapper').each(function() {
            var $wrapper = $(this);
            var $labelBox = $wrapper.find('.livq-match-label-box');
            var imageUrl = $wrapper.data('image-url');
            var correctLabel = $wrapper.data('correct-label');
            var userLabel = userAnswer && userAnswer[imageUrl] ? userAnswer[imageUrl] : null;
            
            // Remove previous feedback classes
            $labelBox.removeClass('correct incorrect');
            $wrapper.find('.livq-match-image-box').removeClass('correct-border incorrect-border');
            
            if (userLabel) {
                if (userLabel.toLowerCase().trim() === correctLabel.toLowerCase().trim()) {
                    // Correct match - green border
                    $labelBox.addClass('correct');
                    $wrapper.find('.livq-match-image-box').addClass('correct-border');
                } else {
                    // Wrong label - red border
                    $labelBox.addClass('incorrect');
                    $wrapper.find('.livq-match-image-box').addClass('incorrect-border');
                }
            } else {
                // No label dropped - red border
                $labelBox.addClass('incorrect');
                $wrapper.find('.livq-match-image-box').addClass('incorrect-border');
            }
        });
        
        // Show overall feedback message
        var $feedbackMsg = $container.find('.livq-feedback-message');
        if ($feedbackMsg.length === 0) {
            $feedbackMsg = $('<div class="livq-feedback-message" style="margin-top: 15px; padding: 12px; border-radius: 6px; font-weight: 600;"></div>');
            $container.append($feedbackMsg);
        }
        
        if (isCorrect) {
            $feedbackMsg.css({
                'background': '#d4edda',
                'color': '#155724',
                'border': '1px solid #c3e6cb'
            }).html('<span style="font-size: 18px; margin-right: 8px;">✓</span> Correct! All labels are matched correctly.');
        } else {
            // Show correct matches
            var correctMatchesHtml = '<div style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px;">';
            correctMatchesHtml += '<strong style="display: block; margin-bottom: 10px; color: #856404;">Correct Matches:</strong>';
            correctMatchesHtml += '<div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">';
            
            for (var imageUrl in correct) {
                if (correct.hasOwnProperty(imageUrl)) {
                    correctMatchesHtml += '<div style="display: flex; flex-direction: column; align-items: center; width: 120px; padding: 8px; background: white; border: 2px solid #28a745; border-radius: 8px;">';
                    correctMatchesHtml += '<img src="' + imageUrl + '" alt="' + correct[imageUrl] + '" style="width: 100px; height: 100px; object-fit: cover; border-radius: 6px; margin-bottom: 5px;">';
                    correctMatchesHtml += '<span style="font-size: 12px; font-weight: 600; color: #28a745;">' + correct[imageUrl] + '</span>';
                    correctMatchesHtml += '</div>';
                }
            }
            
            correctMatchesHtml += '</div></div>';
            
            $feedbackMsg.css({
                'background': '#f8d7da',
                'color': '#721c24',
                'border': '1px solid #f5c6cb'
            }).html('<span style="font-size: 18px; margin-right: 8px;">✗</span> Incorrect. Some labels don\'t match.' + correctMatchesHtml);
        }
    }
    
    function showDragDropImageFeedback($container, question, userAnswer, isCorrect) {
        var correct = JSON.parse(question.correct_answer);
        
        // Mark each answer box as correct or incorrect
        $container.find('.livq-drag-answer-box').each(function(index) {
            var $box = $(this);
            var expectedId = correct[index] ? correct[index].id : null;
            var userItem = userAnswer && userAnswer[index] ? userAnswer[index] : null;
            var userItemId = userItem ? userItem.id : null;
            
            // Remove previous feedback classes
            $box.removeClass('correct incorrect');
            
            if (userItemId && expectedId) {
                if (userItemId === expectedId) {
                    // Correct position - add green checkmark
                    $box.addClass('correct');
                } else {
                    // Wrong image in this position - add red X
                    $box.addClass('incorrect');
                }
            } else if (!userItemId && expectedId) {
                // Empty slot where image should be - add red X
                $box.addClass('incorrect');
            }
        });
        
        // Show overall feedback message
        var $feedbackMsg = $container.find('.livq-feedback-message');
        if ($feedbackMsg.length === 0) {
            $feedbackMsg = $('<div class="livq-feedback-message" style="margin-top: 15px; padding: 12px; border-radius: 6px; font-weight: 600;"></div>');
            $container.append($feedbackMsg);
        }
        
        if (isCorrect) {
            $feedbackMsg.css({
                'background': '#d4edda',
                'color': '#155724',
                'border': '1px solid #c3e6cb'
            }).html('<span style="font-size: 18px; margin-right: 8px;">✓</span> Correct! All images are in the right order.');
        } else {
            // Show correct order visually
            var correctOrderHtml = '<div style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px;">';
            correctOrderHtml += '<strong style="display: block; margin-bottom: 10px; color: #856404;">Correct Order:</strong>';
            correctOrderHtml += '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
            
            correct.forEach(function(item, index) {
                correctOrderHtml += '<div style="display: inline-flex; flex-direction: column; align-items: center; width: 80px; padding: 5px; background: white; border: 2px solid #28a745; border-radius: 6px;">';
                correctOrderHtml += '<span style="font-size: 12px; font-weight: bold; color: #28a745; margin-bottom: 3px;">' + (index + 1) + '</span>';
                correctOrderHtml += '<img src="' + item.url + '" alt="' + (item.label || 'Image') + '" style="width: 100%; height: 50px; object-fit: cover; border-radius: 4px;">';
                if (item.label) {
                    correctOrderHtml += '<span style="font-size: 9px; color: #666; margin-top: 3px; text-align: center;">' + item.label + '</span>';
                }
                correctOrderHtml += '</div>';
            });
            
            correctOrderHtml += '</div></div>';
            
            $feedbackMsg.css({
                'background': '#f8d7da',
                'color': '#721c24',
                'border': '1px solid #f5c6cb'
            }).html('<span style="font-size: 18px; margin-right: 8px;">✗</span> Incorrect. Please check the order of your images.' + correctOrderHtml);
        }
    }
    
    function showQuestionFeedback($container, question, userAnswer, isCorrect) {
        // Remove any existing feedback
        $container.find('.livq-question-feedback').remove();
        
        var feedbackClass = isCorrect ? 'correct' : 'incorrect';
        var feedbackIcon = isCorrect ? '✓' : '✗';
        var feedbackText = isCorrect ? 'Correct!' : 'Incorrect';
        
        var feedbackHtml = '<div class="livq-question-feedback ' + feedbackClass + '">';
        feedbackHtml += '<strong>' + feedbackIcon + ' ' + feedbackText + '</strong>';
        
        if (question.explanation) {
            feedbackHtml += '<div class="explanation">' + question.explanation + '</div>';
        }
        
        if (!isCorrect && question.correct_answer) {
            var correctAnswerText = getCorrectAnswerText(question);
            feedbackHtml += '<div class="correct-answer">Correct answer: ' + correctAnswerText + '</div>';
        }
        
        feedbackHtml += '</div>';
        
        $container.append(feedbackHtml);
    }
    
    function checkAnswer(question, userAnswer) {
        if (question.type === 'true_false') {
            return userAnswer === question.correct_answer;
        } else if (question.type === 'multiple_choice') {
            return userAnswer == question.correct_answer;
        } else if (question.type === 'short_answer') {
             // Basic client-side check, ideally server-side or more robust
             var correct = JSON.parse(question.correct_answer);
             if (Array.isArray(correct)) {
                 return correct.some(ans => ans.toLowerCase().trim() === userAnswer.toLowerCase().trim());
             }
             return correct.toLowerCase().trim() === userAnswer.toLowerCase().trim();
        } else if (question.type === 'fill_blanks') {
            // userAnswer is array of strings
            var correct = JSON.parse(question.correct_answer);
            if (!Array.isArray(userAnswer) || userAnswer.length !== correct.length) return false;
            for (var i = 0; i < correct.length; i++) {
                if (userAnswer[i].toLowerCase().trim() !== correct[i].toLowerCase().trim()) return false;
            }
            return true;
        } else if (question.type === 'drag_drop' || question.type === 'sorting') {
            // userAnswer is array of strings
            var correct = JSON.parse(question.correct_answer);
            // Compare arrays
            return JSON.stringify(userAnswer) === JSON.stringify(correct);
        } else if (question.type === 'drag_drop_image') {
            // userAnswer is array of objects {id, url, label} or null for empty slots
            var correct = JSON.parse(question.correct_answer);
            // Compare IDs - handle null values (empty slots)
            if (!Array.isArray(userAnswer) || !Array.isArray(correct)) return false;
            if (userAnswer.length !== correct.length) return false;
            for (var i = 0; i < correct.length; i++) {
                // If user answer slot is empty (null) or doesn't match correct ID
                if (!userAnswer[i] || !userAnswer[i].id) {
                    return false; // Empty slot means incorrect
                }
                if (userAnswer[i].id !== correct[i].id) {
                    return false; // Wrong image in this position
                }
            }
            return true;
        }
        return false;
    }
    
    function getCorrectAnswerText(question) {
        if (question.type === 'true_false') {
            return question.correct_answer === 'true' ? 'True' : 'False';
        } else if (question.type === 'multiple_choice') {
            var correctIndex = parseInt(question.correct_answer);
            if (question.options && question.options[correctIndex]) {
                return question.options[correctIndex];
            }
        } else if (question.type === 'short_answer') {
            var ans = JSON.parse(question.correct_answer);
            return Array.isArray(ans) ? ans.join(' OR ') : ans;
        } else if (question.type === 'fill_blanks') {
            var ans = JSON.parse(question.correct_answer);
            return ans.join(', ');
        } else if (question.type === 'drag_drop' || question.type === 'sorting') {
            var ans = JSON.parse(question.correct_answer);
            return ans.join(' -> ');
        } else if (question.type === 'drag_drop_image') {
            var ans = JSON.parse(question.correct_answer);
            if (Array.isArray(ans) && ans.length > 0) {
                // Show image labels or positions
                var labels = ans.map(function(item, index) {
                    return (index + 1) + '. ' + (item.label || 'Image ' + (index + 1));
                });
                return labels.join(' → ');
            }
            return 'Correct Order';
        }
        return 'Unknown';
    }
    
    function skipQuestion($container, $modal, settings) {
        console.log('Skip button clicked');
        console.log('Allow skipping:', settings.allow_skipping);
        
        if (settings.allow_skipping) {
            console.log('Skipping question...');
            hideQuizOverlay($container);
            resumeVideo($container);
        } else {
            console.log('Skipping not allowed');
        }
    }
    
    function hideQuizOverlay($container) {
        $container.find('.livq-quiz-overlay').hide();
    }
    
    function resumeVideo($container) {
        var playVideo = $container.data('play-video');
        if (playVideo) {
            playVideo();
        }
    }
    
    function storeAnswer($container, questionId, answer, isCorrect) {
        var answers = $container.data('quiz-answers') || {};
        answers[questionId] = {
            answer: answer,
            isCorrect: isCorrect
        };
        $container.data('quiz-answers', answers);
    }
    
    function setupResultsOverlay($container, settings) {
        var $resultsOverlay = $container.find('.livq-results-overlay');
        var $restartBtn = $resultsOverlay.find('.livq-restart-quiz');
        var $closeBtn = $resultsOverlay.find('.livq-close-results');
        
        // Restart quiz
        $restartBtn.on('click', function() {
            restartQuiz($container);
        });
        
        // Close results
        $closeBtn.on('click', function() {
            $resultsOverlay.hide();
        });
    }
    
    function restartQuiz($container) {
        // Reset quiz state
        $container.data('quiz-answers', {});
        $container.data('quiz-completed', false);
        
        // Reset quiz progression state
        var quizState = {
            activeTimeSlots: [],
            currentTimeSlotIndex: -1,
            currentQuestionIndex: 0,
            answeredQuestions: {}
        };
        $container.data('quiz-state', quizState);
        
        // Hide overlays
        $container.find('.livq-quiz-overlay, .livq-results-overlay').hide();
        
        // Restart video
        var player = $container.data('plyr-player');
        if (player) {
            player.currentTime = 0;
            player.play();
        }
    }
    
    function showQuizResults($container, quizData) {
        var answers = $container.data('quiz-answers') || {};
        var totalQuestions = Object.keys(answers).length;
        var correctAnswers = Object.values(answers).filter(function(answer) {
            return answer.isCorrect;
        }).length;
        
        var $resultsOverlay = $container.find('.livq-results-overlay');
        var $scoreDisplay = $resultsOverlay.find('.livq-score');
        var $totalDisplay = $resultsOverlay.find('.livq-total');
        
        $scoreDisplay.text(correctAnswers);
        $totalDisplay.text(totalQuestions);
        
        $resultsOverlay.show();
        
        // Submit results to server
        submitQuizResults($container, answers, correctAnswers, totalQuestions);
    }
    
    function submitQuizResults($container, answers, score, totalQuestions) {
        var quizId = $container.data('quiz-id');
        // Flatten answers to expected backend format: { [questionId]: userAnswer }
        var payloadAnswers = {};
        try {
            Object.keys(answers || {}).forEach(function(qid) {
                var entry = answers[qid];
                payloadAnswers[qid] = entry && typeof entry === 'object' ? entry.answer : entry;
            });
        } catch (e) {
            payloadAnswers = {};
        }
        
        $.ajax({
            url: livq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'livq_submit_quiz',
                quiz_id: quizId,
                answers: payloadAnswers,
                nonce: livq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Quiz results submitted successfully');
                }
            },
            error: function() {
                console.error('Error submitting quiz results');
            }
        });
    }
});