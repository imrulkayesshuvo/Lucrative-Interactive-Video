/**
 * Lucrative Interactive VideoQuiz - Admin JavaScript
 */

jQuery(document).ready(function ($) {

    // Initialize admin functionality
    initAdmin();

    function initAdmin() {
        // Question form handling
        handleQuestionForm();

        // Video quiz form handling
        handleVideoQuizForm();

        // Video duration detection
        handleVideoDurationDetection();

        // Settings form handling
        handleSettingsForm();

        // Delete confirmations
        handleDeleteConfirmations();

        // Copy shortcode functionality
        handleCopyShortcode();

        // Dynamic form elements
        handleDynamicElements();
    }

    function handleQuestionForm() {
        var $form = $('#livq-question-form');
        if ($form.length === 0) {
            console.log('Question form not found, skipping initialization');
            return;
        }

        $form.on('submit', function (e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();

            // Get question type
            var questionType = $('#question_type').val();

            // Advanced question types that don't use default correct answer field
            var advancedTypes = ['short_answer', 'fill_blanks', 'match_pair', 'match_image_label', 'drag_drop', 'drag_drop_image', 'sorting'];

            // CRITICAL: Remove required attribute from correct_answer for advanced types BEFORE validation
            if (advancedTypes.indexOf(questionType) !== -1) {
                var $correctAnswer = $('#correct_answer');
                $correctAnswer.removeAttr('required');
                $correctAnswer.prop('required', false);
                // Also ensure the parent container is hidden
                $('#correct-answer-container').closest('.livq-form-group').hide();
            }

            // Custom validation for drag-drop-image
            if (questionType === 'drag_drop_image') {
                var imageCount = $('#drag-images-list .drag-image-item').length;
                if (imageCount === 0) {
                    alert('Please add at least one image for the drag & drop question.');
                    return false;
                }
            }

            // Check if form is valid (skip for advanced types that don't use standard fields)
            var isValid = true;

            if (advancedTypes.indexOf(questionType) === -1) {
                isValid = $form[0].checkValidity();
                if (!isValid) {
                    $form[0].reportValidity();
                    return false;
                }
            } else {
                // For advanced types, just check required fields
                var title = $('#question_title').val();
                if (!title || title.trim() === '') {
                    alert('Please enter a question title.');
                    $('#question_title').focus();
                    return false;
                }

                // Custom validation for match_image_label
                if (questionType === 'match_image_label') {
                    var imageCount = $('#match-images-list .match-image-item').length;
                    if (imageCount === 0) {
                        alert('Please add at least one image for the match image to label question.');
                        return false;
                    }
                    // Check if all images have labels
                    var allHaveLabels = true;
                    $('#match-images-list input[name="match_image_labels[]"]').each(function () {
                        if (!$(this).val() || $(this).val().trim() === '') {
                            allHaveLabels = false;
                            return false; // break loop
                        }
                    });
                    if (!allHaveLabels) {
                        alert('Please add labels for all images.');
                        return false;
                    }
                }
            }

            console.log('Form submission started for type:', questionType);

            $submitBtn.prop('disabled', true).html('<span class="livq-spinner"></span> Processing...');

            var formData = new FormData(this);
            formData.append('action', 'livq_save_question');
            formData.append('nonce', livq_admin_ajax.nonce);

            // Debug: Log form data
            console.log('Form data being sent:');
            for (var pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }

            $.ajax({
                url: livq_admin_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    console.log('AJAX response:', response);
                    if (response.success) {
                        showNotification(response.data, 'success');
                        setTimeout(function () {
                            window.location.href = '?page=livq-dashboard&tab=questions';
                        }, 1500);
                    } else {
                        var errorMsg = response.data || 'An error occurred while saving the question';
                        console.error('Save error:', errorMsg);
                        showNotification(errorMsg, 'error');
                        $submitBtn.prop('disabled', false).text(originalText);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    console.error('Response:', xhr.responseText);
                    var errorMsg = 'An error occurred while saving the question. Please check the console for details.';
                    if (xhr.responseText) {
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            if (errorResponse.data) {
                                errorMsg = errorResponse.data;
                            }
                        } catch (e) {
                            // Ignore parse errors
                        }
                    }
                    showNotification(errorMsg, 'error');
                    $submitBtn.prop('disabled', false).text(originalText);
                },
                complete: function () {
                    // Only re-enable if not successful (success redirects)
                    if (!$submitBtn.prop('disabled')) {
                        // Already handled in error/success
                    }
                }
            });

            return false;
        });

        // Also handle button click directly as fallback
        $form.find('button[type="submit"]').on('click', function (e) {
            // Let the form submit handler take over
            // This ensures the form submit event fires even if button is clicked
            var $btn = $(this);
            if (!$btn.closest('form').length) {
                // If button is outside form, find the form
                var $form = $('#livq-question-form');
                if ($form.length) {
                    $form.submit();
                }
            }
        });

        // Handle question type change
        $('#question_type').on('change', function () {
            var type = $(this).val();
            var $optionsContainer = $('#options-container');
            var $correctAnswer = $('#correct_answer');
            var $correctAnswerGroup = $('#correct-answer-container').closest('.livq-form-group');

            // Advanced question types that don't use default correct answer field
            var advancedTypes = ['short_answer', 'fill_blanks', 'match_pair', 'match_image_label', 'drag_drop', 'drag_drop_image', 'sorting'];

            if (advancedTypes.indexOf(type) !== -1) {
                // Remove required attribute for advanced types to prevent HTML5 validation errors
                $correctAnswer.removeAttr('required');
                $correctAnswer.prop('required', false);
                // Hide the field group
                $correctAnswerGroup.hide();
            } else {
                // Show and add required attribute back for standard types
                $correctAnswerGroup.show();
                $correctAnswer.prop('required', true);
            }

            if (type === 'multiple_choice') {
                $optionsContainer.show();
                // Make option inputs required for multiple choice
                $optionsContainer.find('input[name="options[]"]').prop('required', true);
                if ($('#options-list .option-item').length === 0) {
                    addOption();
                    addOption();
                }
            } else {
                $optionsContainer.hide();
                // Remove required attribute for true/false questions
                $optionsContainer.find('input[name="options[]"]').prop('required', false);
                updateCorrectAnswerOptions();
            }
        });

        // Also handle on page load if question type is already set
        var initialType = $('#question_type').val();
        if (initialType) {
            $('#question_type').trigger('change');
        }

        // Add option button - DISABLED FOR FREE VERSION
        // $('#add-option').on('click', function() {
        //     addOption();
        // });

        // Remove option
        $(document).on('click', '.remove-option', function () {
            var currentOptions = $('#options-list .option-item').length;
            if (currentOptions <= 2) {
                alert('You must have at least 2 options for a multiple choice question.');
                return;
            }
            $(this).closest('.option-item').remove();
            updateCorrectAnswerOptions();
        });

        // Update correct answer options when options change
        $(document).on('input', 'input[name="options[]"]', function () {
            updateCorrectAnswerOptions();
        });
    }

    function addOption() {
        // FREE VERSION LIMIT: Maximum 4 options
        var currentOptions = $('#options-list .option-item').length;
        if (currentOptions >= 4) {
            alert('Free version is limited to 4 options. Upgrade to Pro for unlimited options.');
            return;
        }

        var index = currentOptions;
        var isMultipleChoice = $('#question_type').val() === 'multiple_choice';
        var requiredAttr = isMultipleChoice ? 'required' : '';
        var optionHtml = '<div class="option-item">' +
            '<input type="text" name="options[]" placeholder="Option ' + (index + 1) + '" ' + requiredAttr + '>' +
            '<button type="button" class="remove-option">Remove</button>' +
            '</div>';
        $('#options-list').append(optionHtml);
        updateCorrectAnswerOptions();
    }

    function updateCorrectAnswerOptions() {
        var $correctAnswer = $('#correct_answer');
        var $options = $('input[name="options[]"]');
        var currentValue = $correctAnswer.val();

        $correctAnswer.empty();

        if ($('#question_type').val() === 'true_false') {
            $correctAnswer.append('<option value="true">True</option>');
            $correctAnswer.append('<option value="false">False</option>');
        } else {
            $options.each(function (index) {
                var value = $(this).val();
                if (value) {
                    $correctAnswer.append('<option value="' + index + '">' + value + '</option>');
                }
            });
        }

        if ($correctAnswer.find('option[value="' + currentValue + '"]').length) {
            $correctAnswer.val(currentValue);
        }
    }

    function handleVideoQuizForm() {
        $('#livq-video-quiz-form').on('submit', function (e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();

            $submitBtn.prop('disabled', true).html('<span class="livq-spinner"></span> Processing...');

            var formData = new FormData(this);
            formData.append('action', 'livq_save_video_quiz');
            formData.append('nonce', livq_admin_ajax.nonce);

            // Debug: Log form data before submission
            console.log('=== LIVQ Form Submission Debug ===');
            console.log('Form data:', formData);
            var formDataObj = {};
            for (var pair of formData.entries()) {
                if (formDataObj[pair[0]]) {
                    if (Array.isArray(formDataObj[pair[0]])) {
                        formDataObj[pair[0]].push(pair[1]);
                    } else {
                        formDataObj[pair[0]] = [formDataObj[pair[0]], pair[1]];
                    }
                } else {
                    formDataObj[pair[0]] = pair[1];
                }
            }
            console.log('Form data object:', formDataObj);
            console.log('Video Type:', $('#video_type').val());
            console.log('Tutor LMS Lesson:', $('#tutor_lms_lesson').val());
            console.log('Video URL:', $('#video_url').val());
            console.log('Video URL Standard:', $('#video_url_standard').val());
            console.log('Time Slots:', formDataObj['time_slots']);
            console.log('=== End Form Submission Debug ===');

            $.ajax({
                url: livq_admin_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    console.log('AJAX Success Response:', response);
                    if (response.success) {
                        showNotification(response.data, 'success');
                        setTimeout(function () {
                            window.location.href = '?page=livq-dashboard&tab=videos';
                        }, 1500);
                    } else {
                        console.error('AJAX Error Response:', response);
                        showNotification(response.data || 'An error occurred', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Request Error:', status, error);
                    console.error('Response:', xhr.responseText);
                    showNotification('An error occurred while saving the video quiz: ' + error, 'error');
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });

        // Add time slot button
        $('#add-time-slot').on('click', function () {
            addTimeSlot();
        });

        // Remove time slot
        $(document).on('click', '.remove-time-slot', function () {
            $(this).closest('.time-slot-item').remove();
        });
    }

    function addTimeSlot() {
        var index = $('#time-slots-container .time-slot-item').length;
        var timeSlotHtml = '<div class="time-slot-item">' +
            '<div class="time-slot-header">' +
            '<label>Time Slot ' + (index + 1) + '</label>' +
            '<button type="button" class="remove-time-slot">Remove</button>' +
            '</div>' +
            '<div class="time-slot-content">' +
            '<div class="time-input-group">' +
            '<input type="number" name="time_slots[' + index + '][time]" placeholder="Time" min="0" class="time-input">' +
            '<select name="time_slots[' + index + '][unit]" class="time-unit-select">' +
            '<option value="seconds">Seconds</option>' +
            '<option value="minutes">Minutes</option>' +
            '<option value="hours">Hours</option>' +
            '</select>' +
            '</div>' +
            '<select name="time_slots[' + index + '][questions][]" multiple>' +
            getQuestionsOptions() +
            '</select>' +
            '</div>' +
            '</div>';
        $('#time-slots-container').append(timeSlotHtml);

        // Update remaining time after adding
        setTimeout(function () {
            updateRemainingTime();
        }, 100);
    }

    function getQuestionsOptions() {
        var options = '<option value="">Select questions...</option>';

        // Use preloaded questions if available
        if (window.livqQuestions && window.livqQuestions.length > 0) {
            window.livqQuestions.forEach(function (question) {
                options += '<option value="' + question.id + '">' + question.title + '</option>';
            });
        } else {
            // Fallback: Load questions via AJAX
            $.ajax({
                url: livq_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'livq_get_questions',
                    nonce: livq_admin_ajax.nonce
                },
                async: false, // Make it synchronous for this use case
                success: function (response) {
                    if (response.success && response.data) {
                        response.data.forEach(function (question) {
                            options += '<option value="' + question.id + '">' + question.title + '</option>';
                        });
                    }
                },
                error: function () {
                    console.error('Error loading questions');
                }
            });
        }

        return options;
    }

    function handleSettingsForm() {
        $('#livq-settings-form').on('submit', function (e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();

            $submitBtn.prop('disabled', true).html('<span class="livq-spinner"></span> Saving...');

            var formData = new FormData(this);
            formData.append('action', 'livq_save_settings');
            formData.append('nonce', livq_admin_ajax.nonce);

            $.ajax({
                url: livq_admin_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        showNotification(response.data, 'success');
                    } else {
                        showNotification(response.data || 'An error occurred', 'error');
                    }
                },
                error: function () {
                    showNotification('An error occurred while saving settings', 'error');
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    function handleDeleteConfirmations() {
        // Delete question
        $(document).on('click', '.livq-delete-question', function () {
            if (confirm('Are you sure you want to delete this question? This action cannot be undone.')) {
                var questionId = $(this).data('id');
                deleteQuestion(questionId);
            }
        });

        // Delete video quiz
        $(document).on('click', '.livq-delete-quiz', function () {
            if (confirm('Are you sure you want to delete this video quiz? This action cannot be undone.')) {
                var quizId = $(this).data('id');
                deleteVideoQuiz(quizId);
            }
        });
    }

    function deleteQuestion(questionId) {
        $.ajax({
            url: livq_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'livq_delete_question',
                question_id: questionId,
                nonce: livq_admin_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    showNotification(response.data, 'success');
                    setTimeout(function () {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(response.data || 'An error occurred', 'error');
                }
            },
            error: function () {
                showNotification('An error occurred while deleting the question', 'error');
            }
        });
    }

    function deleteVideoQuiz(quizId) {
        $.ajax({
            url: livq_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'livq_delete_video_quiz',
                quiz_id: quizId,
                nonce: livq_admin_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    showNotification(response.data, 'success');
                    setTimeout(function () {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(response.data || 'An error occurred', 'error');
                }
            },
            error: function () {
                showNotification('An error occurred while deleting the video quiz', 'error');
            }
        });
    }

    function handleCopyShortcode() {
        $(document).on('click', '.copy-shortcode', function () {
            var shortcode = $(this).data('shortcode');

            // Create temporary input element
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(shortcode).select();
            document.execCommand('copy');
            $temp.remove();

            // Show feedback
            var $btn = $(this);
            var originalText = $btn.text();
            $btn.text('Copied!').addClass('copied');

            setTimeout(function () {
                $btn.text(originalText).removeClass('copied');
            }, 2000);
        });
    }

    function handleDynamicElements() {
        // Initialize any dynamic elements that need setup
        updateCorrectAnswerOptions();

        // Load questions for video quiz form
        loadQuestionsForVideoQuiz();
    }

    function loadQuestionsForVideoQuiz() {
        // If we're on the video quiz form, preload questions
        if ($('#livq-video-quiz-form').length) {
            $.ajax({
                url: livq_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'livq_get_questions',
                    nonce: livq_admin_ajax.nonce
                },
                success: function (response) {
                    if (response.success && response.data) {
                        // Store questions globally for use in time slots
                        window.livqQuestions = response.data;
                    }
                },
                error: function () {
                    console.error('Error loading questions for video quiz');
                }
            });
        }
    }

    function handleVideoDurationDetection() {
        var $videoUrlInput = $('#video_url');
        var $videoTypeSelect = $('#video_type');
        var $previewContainer = $('#video-preview-container');
        var $previewWrapper = $('#video-preview-wrapper');
        var $previewPlaceholder = $('#video-preview-placeholder');
        var $durationInfo = $('#video-duration-info');
        var $durationText = $('#duration-text');
        var $remainingTimeDisplay = $('#remaining-time-display');

        // Create remaining time display if it doesn't exist
        if ($remainingTimeDisplay.length === 0) {
            $durationInfo.after('<div id="remaining-time-display" class="livq-remaining-info" style="display: none; margin-top: 10px; padding: 10px; background: #fff3cd; border-radius: 5px; border-left: 4px solid #ffc107;"></div>');
            $remainingTimeDisplay = $('#remaining-time-display');
        }

        // Handle video URL change (for standard video types)
        $('#video_url_standard').on('input', function () {
            var videoUrl = $(this).val().trim();
            var videoType = $videoTypeSelect.val();

            // Always keep the canonical hidden value in sync
            $('#video_url').val(videoUrl);

            if (videoUrl && videoType && videoType !== 'tutor_lms') {
                showVideoPreview(videoUrl, videoType);
            } else {
                hideVideoPreview();
            }
        });

        // Handle video type change
        $videoTypeSelect.on('change', function () {
            var videoType = $(this).val();
            var $tutorLmsSelector = $('#tutor-lms-video-selector');
            var $standardUrlInput = $('#standard-video-url-input');
            var $tutorLmsSelect = $('#tutor_lms_lesson');
            var $videoUrlHidden = $('#video_url');
            var $videoUrlStandard = $('#video_url_standard');

            // Show/hide appropriate fields based on video type
            if (videoType === 'tutor_lms') {
                $tutorLmsSelector.show();
                $standardUrlInput.hide();
                $videoUrlStandard.removeAttr('required');
                $tutorLmsSelect.attr('required', 'required');

                // Handle Tutor LMS lesson selection - use off() to prevent duplicate handlers
                $tutorLmsSelect.off('change.tutorlms').on('change.tutorlms', function () {
                    var lessonId = $(this).val();
                    var $tutorLmsLessonHidden = $('#tutor_lms_lesson_id');
                    if (lessonId) {
                        $videoUrlHidden.val(lessonId);
                        if ($tutorLmsLessonHidden.length) {
                            $tutorLmsLessonHidden.val(lessonId);
                        }
                        // Load video preview for Tutor LMS lesson
                        loadTutorLmsVideoPreview(lessonId);
                    } else {
                        $videoUrlHidden.val('');
                        if ($tutorLmsLessonHidden.length) {
                            $tutorLmsLessonHidden.val('');
                        }
                        hideVideoPreview();
                    }
                });

                // Set initial value if editing - check both hidden fields
                var savedLessonId = $videoUrlHidden.val() || $('#tutor_lms_lesson_id').val();
                if (savedLessonId && !$tutorLmsSelect.val()) {
                    $tutorLmsSelect.val(savedLessonId);
                    var $tutorLmsLessonHidden = $('#tutor_lms_lesson_id');
                    if ($tutorLmsLessonHidden.length) {
                        $tutorLmsLessonHidden.val(savedLessonId);
                    }
                    // Load preview if lesson ID exists
                    if (savedLessonId) {
                        loadTutorLmsVideoPreview(savedLessonId);
                    }
                }

                // Ensure canonical hidden video_url holds lesson ID
                if ($tutorLmsSelect.val()) {
                    $videoUrlHidden.val($tutorLmsSelect.val());
                }
            } else {
                $tutorLmsSelector.hide();
                $standardUrlInput.show();
                $tutorLmsSelect.removeAttr('required');
                $videoUrlStandard.attr('required', 'required');

                var videoUrl = $videoUrlStandard.val().trim();

                // Sync canonical hidden video_url with standard field
                $('#video_url').val(videoUrl);

                if (videoUrl && videoType) {
                    showVideoPreview(videoUrl, videoType);
                } else {
                    hideVideoPreview();
                }
            }
        });

        // Trigger change on page load if Tutor LMS is selected
        if ($videoTypeSelect.val() === 'tutor_lms') {
            $videoTypeSelect.trigger('change');
            // Also ensure the lesson dropdown is set correctly
            var savedLessonId = $('#video_url').val() || $('#tutor_lms_lesson_id').val();
            if (savedLessonId) {
                $('#tutor_lms_lesson').val(savedLessonId);
                var $tutorLmsLessonHidden = $('#tutor_lms_lesson_id');
                if ($tutorLmsLessonHidden.length) {
                    $tutorLmsLessonHidden.val(savedLessonId);
                }
                // Keep canonical hidden in sync
                $('#video_url').val(savedLessonId);
                // Load video preview for the saved lesson
                loadTutorLmsVideoPreview(savedLessonId);
            }
        } else {
            // Non Tutor: ensure canonical hidden matches visible input on load
            var stdUrl = $('#video_url_standard').val();
            if (stdUrl) {
                $('#video_url').val(stdUrl);
                // Show video preview on page load for existing videos
                var videoType = $videoTypeSelect.val();
                if (videoType && stdUrl) {
                    showVideoPreview(stdUrl, videoType);
                }
            } else {
                // If standard field is empty but hidden field has value, sync them
                var hiddenUrl = $('#video_url').val();
                if (hiddenUrl) {
                    $('#video_url_standard').val(hiddenUrl);
                    var videoType = $videoTypeSelect.val();
                    if (videoType) {
                        showVideoPreview(hiddenUrl, videoType);
                    }
                }
            }
        }

        // Additional check: Trigger video preview on page load if URL exists
        setTimeout(function () {
            var currentUrl = $('#video_url_standard').val();
            var currentType = $videoTypeSelect.val();
            var hiddenUrl = $('#video_url').val();

            console.log('Page load video check:', {
                currentUrl: currentUrl,
                currentType: currentType,
                hiddenUrl: hiddenUrl,
                standardFieldExists: $('#video_url_standard').length > 0,
                previewContainerExists: $('#video-preview-container').length > 0
            });

            if (currentUrl && currentType && currentType !== 'tutor_lms') {
                console.log('Triggering video preview on load:', currentUrl, currentType);
                showVideoPreview(currentUrl, currentType);
            } else if (currentType === 'tutor_lms') {
                var lessonId = $('#tutor_lms_lesson').val() || hiddenUrl;
                if (lessonId) {
                    console.log('Triggering Tutor LMS video preview on load:', lessonId);
                    loadTutorLmsVideoPreview(lessonId);
                }
            }
        }, 1000); // Increased timeout to ensure DOM is ready

        // Handle time slot changes
        $(document).on('input', 'input[name*="[time]"]', function () {
            updateRemainingTime();
        });

        $(document).on('click', '.remove-time-slot', function () {
            setTimeout(function () {
                updateRemainingTime();
            }, 100);
        });

        $(document).on('click', '#add-time-slot', function () {
            setTimeout(function () {
                updateRemainingTime();
            }, 100);
        });
    }

    function loadTutorLmsVideoPreview(lessonId) {
        var $previewContainer = $('#video-preview-container');
        var $previewWrapper = $('#video-preview-wrapper');
        var $previewPlaceholder = $('#video-preview-placeholder');
        var $durationInfo = $('#video-duration-info');
        var $durationText = $('#duration-text');

        // Show preview container
        $previewContainer.show();
        $previewPlaceholder.show();
        $previewPlaceholder.html('<span class="dashicons dashicons-video-alt3"></span><p>Loading Tutor LMS lesson video...</p>');
        $durationInfo.hide();

        // Clear previous video
        $previewWrapper.find('iframe, video').remove();

        // Load video via AJAX
        $.ajax({
            url: livq_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'livq_get_tutor_lms_video',
                nonce: livq_admin_ajax.nonce,
                lesson_id: lessonId
            },
            success: function (response) {
                if (response.success && response.data.video_url) {
                    var videoUrl = response.data.video_url;
                    var videoSource = response.data.video_source || 'youtube';

                    // Show preview based on video source
                    if (videoSource === 'youtube' || videoSource === 'vimeo' || videoSource === 'html5') {
                        showVideoPreview(videoUrl, videoSource === 'html5' ? 'mp4' : videoSource);
                    } else {
                        $previewPlaceholder.html('<span class="dashicons dashicons-video-alt3"></span><p>Tutor LMS lesson selected. Video will be displayed on the frontend.</p>');
                    }
                } else {
                    $previewPlaceholder.html('<span class="dashicons dashicons-warning"></span><p>This lesson does not have a video or video could not be loaded.</p>');
                }
            },
            error: function () {
                $previewPlaceholder.html('<span class="dashicons dashicons-warning"></span><p>Error loading Tutor LMS lesson video.</p>');
            }
        });
    }

    function showVideoPreview(videoUrl, videoType) {
        console.log('showVideoPreview called with:', videoUrl, videoType);

        var $previewContainer = $('#video-preview-container');
        var $previewWrapper = $('#video-preview-wrapper');
        var $previewPlaceholder = $('#video-preview-placeholder');
        var $durationInfo = $('#video-duration-info');
        var $durationText = $('#duration-text');

        console.log('Preview container found:', $previewContainer.length);

        // Show preview container
        $previewContainer.show();
        $previewPlaceholder.hide();
        $durationInfo.hide();

        // Clear previous video
        $previewWrapper.find('iframe, video').remove();

        // Create embedded video based on type
        var embedHtml = '';
        var videoId = '';

        if (videoType === 'youtube') {
            videoId = extractYouTubeId(videoUrl);
            console.log('YouTube ID extracted:', videoId);
            if (videoId) {
                // Create a better preview with thumbnail and iframe options
                embedHtml = '<div class="youtube-admin-preview">' +
                    // Thumbnail preview with play button
                    '<div class="youtube-thumbnail-preview" style="position:relative; width:100%; height:200px; background:#000; margin-bottom:10px; cursor:pointer; border-radius:4px; overflow:hidden;" onclick="this.style.display=\'none\'; document.getElementById(\'youtube-iframe-' + videoId + '\').style.display=\'block\';">' +
                    '<img src="https://img.youtube.com/vi/' + videoId + '/maxresdefault.jpg" style="width:100%; height:100%; object-fit:cover;" onerror="this.src=\'https://img.youtube.com/vi/' + videoId + '/hqdefault.jpg\'">' +
                    '<div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); background:rgba(255,0,0,0.9); color:white; padding:15px 25px; border-radius:8px; font-size:14px; font-weight:bold; box-shadow:0 2px 10px rgba(0,0,0,0.3);">' +
                    '<span class="dashicons dashicons-controls-play" style="font-size:24px; margin-right:8px; vertical-align:middle;"></span>Click to Load Video Player' +
                    '</div>' +
                    '<div style="position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.7); color:white; padding:5px 10px; border-radius:4px; font-size:12px;">YouTube Preview</div>' +
                    '</div>' +
                    // Iframe (hidden initially)
                    '<div id="youtube-iframe-' + videoId + '" style="display:none;">' +
                    '<iframe id="video-preview-iframe" width="100%" height="200" src="https://www.youtube-nocookie.com/embed/' + videoId + '?enablejsapi=1&rel=0&modestbranding=1&autoplay=1" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>' +
                    '<div style="text-align:center; margin-top:5px;">' +
                    '<a href="' + videoUrl + '" target="_blank" class="button button-small">Open in YouTube</a>' +
                    '<button type="button" class="button button-small" onclick="document.getElementById(\'youtube-iframe-' + videoId + '\').style.display=\'none\'; document.querySelector(\'.youtube-thumbnail-preview\').style.display=\'block\';" style="margin-left:10px;">Show Thumbnail</button>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            }
        } else if (videoType === 'vimeo') {
            videoId = extractVimeoId(videoUrl);
            console.log('Vimeo ID extracted:', videoId);
            if (videoId) {
                embedHtml = '<iframe id="video-preview-iframe" width="100%" height="200" src="https://player.vimeo.com/video/' + videoId + '?api=1" frameborder="0" allowfullscreen></iframe>';
            }
        } else if (videoType === 'mp4') {
            console.log('MP4 video URL:', videoUrl);
            embedHtml = '<video id="video-preview-iframe" width="100%" height="200" controls><source src="' + videoUrl + '" type="video/mp4">Your browser does not support the video tag.</video>';
        }

        if (embedHtml) {
            console.log('Embedding video HTML');
            $previewWrapper.html(embedHtml);

            // Set up duration detection
            setupDurationDetection(videoType);
        } else {
            console.log('Invalid video URL format');
            $previewPlaceholder.show();
            $previewPlaceholder.find('p').text('Invalid video URL format');
        }
    }

    function hideVideoPreview() {
        $('#video-preview-container').hide();
        $('#video-duration-info').hide();
        $('#remaining-time-display').hide();
        window.videoDuration = null;
    }

    function setupDurationDetection(videoType) {
        var $durationInfo = $('#video-duration-info');
        var $durationText = $('#duration-text');

        $durationInfo.show();
        $durationText.text('Loading...');

        if (videoType === 'youtube') {
            setupYouTubeDurationDetection();
        } else if (videoType === 'vimeo') {
            setupVimeoDurationDetection();
        } else if (videoType === 'mp4') {
            setupMP4DurationDetection();
        }
    }

    function setupYouTubeDurationDetection() {
        // YouTube API is loaded globally
        if (typeof YT !== 'undefined' && YT.Player) {
            var player = new YT.Player('video-preview-iframe', {
                events: {
                    'onReady': function (event) {
                        var duration = event.target.getDuration();
                        if (duration > 0) {
                            window.videoDuration = duration;
                            updateDurationDisplay(duration);
                            updateRemainingTime();
                        }
                    }
                }
            });
        } else {
            // Fallback: try to get duration from iframe
            setTimeout(function () {
                try {
                    var iframe = document.getElementById('video-preview-iframe');
                    if (iframe && iframe.contentWindow) {
                        // This is a simplified approach - in practice, you'd need proper API access
                        $durationText.text('Duration detection requires YouTube API');
                    }
                } catch (e) {
                    $durationText.text('Unable to detect duration');
                }
            }, 2000);
        }
    }

    function setupVimeoDurationDetection() {
        var iframe = document.getElementById('video-preview-iframe');
        if (iframe) {
            // Vimeo player API
            var player = new Vimeo.Player(iframe);
            player.getDuration().then(function (duration) {
                window.videoDuration = duration;
                updateDurationDisplay(duration);
                updateRemainingTime();
            }).catch(function (error) {
                $durationText.text('Unable to detect duration');
            });
        }
    }

    function setupMP4DurationDetection() {
        var video = document.getElementById('video-preview-iframe');
        if (video) {
            video.addEventListener('loadedmetadata', function () {
                var duration = video.duration;
                if (duration > 0) {
                    window.videoDuration = duration;
                    updateDurationDisplay(duration);
                    updateRemainingTime();
                }
            });

            video.addEventListener('error', function () {
                $durationText.text('Unable to load video');
            });
        }
    }

    function updateDurationDisplay(duration) {
        var $durationText = $('#duration-text');
        var durationText = formatDuration(duration);
        $durationText.text(durationText);
    }

    function extractYouTubeId(url) {
        var regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/;
        var match = url.match(regExp);
        // YouTube IDs are typically 11 characters, but be more lenient for partial URLs
        return (match && match[2] && match[2].length >= 10) ? match[2] : null;
    }

    function extractVimeoId(url) {
        var regExp = /vimeo\.com\/(\d+)/;
        var match = url.match(regExp);
        return match ? match[1] : null;
    }

    function updateRemainingTime() {
        var $remainingTimeDisplay = $('#remaining-time-display');

        if (!window.videoDuration) {
            $remainingTimeDisplay.hide();
            return;
        }

        var totalDuration = window.videoDuration;
        var usedTime = 0;

        // Calculate total time used by all time slots
        $('input[name*="[time]"]').each(function () {
            var time = parseInt($(this).val()) || 0;
            usedTime += time;
        });

        var remainingTime = totalDuration - usedTime;
        var remainingText = formatDuration(Math.max(0, remainingTime));
        var usedText = formatDuration(usedTime);

        if (remainingTime > 0) {
            $remainingTimeDisplay.html(
                '<strong>⏱️ Time Usage:</strong> ' + usedText + ' used, ' + remainingText + ' remaining'
            ).show();
        } else if (usedTime > totalDuration) {
            $remainingTimeDisplay.html(
                '<strong>⚠️ Time Overrun:</strong> ' + usedText + ' used (exceeds video duration by ' + formatDuration(usedTime - totalDuration) + ')'
            ).show();
        } else {
            $remainingTimeDisplay.html(
                '<strong>✅ All Time Used:</strong> ' + usedText + ' (no remaining time)'
            ).show();
        }
    }

    function formatDuration(seconds) {
        if (!seconds || seconds < 0) return '0:00';

        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var secs = seconds % 60;

        if (hours > 0) {
            return hours + ':' + (minutes < 10 ? '0' : '') + minutes + ':' + (secs < 10 ? '0' : '') + secs;
        } else {
            return minutes + ':' + (secs < 10 ? '0' : '') + secs;
        }
    }

    function showNotification(message, type) {
        var notificationClass = 'livq-notification ' + type;
        var $notification = $('<div class="' + notificationClass + '">' + message + '</div>');

        $('.livq-content').prepend($notification);

        setTimeout(function () {
            $notification.fadeOut(function () {
                $(this).remove();
            });
        }, 5000);
    }

    // Initialize question type change on page load
    $('#question_type').trigger('change');

    // Also handle existing options on page load
    var currentType = $('#question_type').val();
    if (currentType === 'true_false') {
        $('#options-container input[name="options[]"]').prop('required', false);
    } else if (currentType === 'multiple_choice') {
        $('#options-container input[name="options[]"]').prop('required', true);
    }

    // Force video preview initialization on page load
    setTimeout(function () {
        var videoUrl = $('#video_url_standard').val();
        var videoType = $('#video_type').val();

        console.log('Force video preview check:', {
            videoUrl: videoUrl,
            videoType: videoType,
            urlLength: videoUrl ? videoUrl.length : 0,
            previewContainerExists: $('#video-preview-container').length > 0
        });

        if (videoUrl && videoType && videoType !== 'tutor_lms') {
            console.log('Force triggering video preview');
            showVideoPreview(videoUrl, videoType);
        } else if (videoType === 'tutor_lms') {
            var lessonId = $('#tutor_lms_lesson').val() || $('#video_url').val();
            if (lessonId) {
                console.log('Force triggering Tutor LMS video preview');
                loadTutorLmsVideoPreview(lessonId);
            }
        }
    }, 2000); // Wait 2 seconds to ensure everything is loaded
});
