jQuery(document).ready(function($) {
    
    let uploadedFiles = []; // Store metadata for uploaded files
    let pendingUploadsMap = new Map(); // Track pending uploads with unique IDs
    let pendingUploads = 0;
    let dropArea = $('#edd-drag-drop-area');

	dropArea.on('dragover', function(event) {
        event.preventDefault();
        dropArea.addClass('drag-over');
    });

    dropArea.on('dragleave', function(event) {
        event.preventDefault();
        dropArea.removeClass('drag-over');
    });

    dropArea.on('drop', function(event) {
        event.preventDefault();
        dropArea.removeClass('drag-over');
        let files = event.originalEvent.dataTransfer.files;
        edd_bulk_lite_upload_handleFileUploads(files);
    });

    $('#edd-file-upload').on('change', function(event) {
        let files = event.target.files;
        edd_bulk_lite_upload_handleFileUploads(files);
    });
	
	var pendingProcesses = 0;
  	var uploadQueue = [];
	var activeUploads = 0;
	var maxSimultaneous = 10; // Limit to 10 simultaneous uploads
    const generateTagsButton = $("#generate-tags-button");
    const extractMetaTagsButton = $('#extract-metadata-button');
    const generateImportFileButton = $('#edd-generate-import-file');
    const generateTitlesButton = $('#generate-titles-button');
    const generateDescriptionButton = $('#generate-descriptions-button');
    const addTagsButton = document.getElementById('add-tags-button');
	const removeTagsButton = document.getElementById('remove-tags-button');
	const addCategoryButton = document.getElementById('add-category-button');
	const removeCategoryButton = document.getElementById('remove-category-button');
	const bulkUpdatePricesButton = document.getElementById('bulk-update-prices-button');
	
	$(document).on('click', '#removeSelectedItems', function () {
		// Check if any checkboxes are selected
		const selectedCheckboxes = $('#edd-upload-tbody .bulkActionSelections:checked').not('#actionSelectAll');
		console.log('Selected checkboxes:', selectedCheckboxes);

		if (selectedCheckboxes.length === 0) {
			alert('No items selected for removal.');
			return;
		}

		// Prevent multiple popups by checking if one already exists
		if ($('#remove-items-popup').length > 0) { // Use a unique popup ID
			console.log('Popup already exists.');
			return; // Popup already exists
		}

		// Create the popup HTML with a unique ID
		const popup = $(`
			<div class="edd_bulk_lite_upload_delete_popup_overlay" id="remove-items-popup">
				<div class="edd_bulk_lite_upload_delete_popup">
					<p>Are you sure you want to remove the selected items?</p>
					<button class="edd_bulk_lite_upload_popup_yes">Yes</button>
					<button class="edd_bulk_lite_upload_popup_cancel">Cancel</button>
				</div>
			</div>
		`);
		console.log('Popup created:', popup);

		// Append the popup to the body
		$('body').append(popup);
		console.log('Popup appended to body.');

		// Event handler for the 'Yes' button
		popup.find('.edd_bulk_lite_upload_popup_yes').on('click', function () {
			// Iterate over each selected checkbox and remove its corresponding row
			selectedCheckboxes.each(function () {
				var checkboxId = $(this).attr('id'); // e.g., 'actionupload-1734045520982-753'
				var rowId = checkboxId.replace(/^action/, ''); // Remove 'action' prefix to get 'upload-1734045520982-753'
				console.log('Removing row with ID:', rowId);
				$('#' + rowId).remove(); // Remove the row with the corresponding ID
			});

			// Remove the popup
			popup.remove();
			console.log('Popup removed after confirmation.');
		});

		// Event handler for the 'Cancel' button
		popup.find('.edd_bulk_lite_upload_popup_cancel').on('click', function () {
			// Remove the popup without taking any action
			popup.remove();
			console.log('Popup removed after cancellation.');
		});
	});

	if (addTagsButton) {
		addTagsButton.addEventListener('click', function () {
			const newTagsInput = document.getElementById('tagsChangeInput');
			const newTags = newTagsInput ? newTagsInput.value.trim() : '';

			if (!newTagsInput) {
				console.error('Tags input element not found.');
				return;
			}

			if (newTags === '') {
				alert('Please enter some tags to add.');
				return;
			}

			// Select only the checked checkboxes within #edd-upload-tbody, excluding #actionSelectAll
			const selectedCheckboxes = document.querySelectorAll('#edd-upload-tbody .bulkActionSelections:checked:not(#actionSelectAll)');
			console.log('Selected checkboxes:', selectedCheckboxes);

			if (selectedCheckboxes.length === 0) {
				alert('No items selected for tag addition.');
				return;
			}

			selectedCheckboxes.forEach(function (checkbox) {
				const checkboxId = checkbox.id; // e.g., 'actionupload-1734045520982-753'
				const rowId = checkboxId.replace(/^action/, ''); // 'upload-1734045520982-753'
				const row = document.getElementById(rowId);

				if (row) {
					const tagsInput = row.querySelector('.tags-input');
					if (tagsInput) {
						const existingTags = tagsInput.value.trim();
						if (existingTags) {
							tagsInput.value = `${existingTags}, ${newTags}`;
						} else {
							tagsInput.value = newTags;
						}
						console.log(`Tags updated for row ID: ${rowId}`);
					} else {
						console.warn(`.tags-input not found in row ID: ${rowId}`);
					}
				} else {
					console.warn(`Row with ID '${rowId}' not found.`);
				}
			});

			newTagsInput.value = '';
			alert('Tags added to selected uploads!');
		});
	}



	if (removeTagsButton) {
		removeTagsButton.addEventListener('click', function () {
			const removeTagsInput = document.getElementById('tagsChangeInput');
			const tagsToRemove = removeTagsInput ? removeTagsInput.value.split(',').map(tag => tag.trim()) : [];

			if (!removeTagsInput) {
				console.error('Tags input element not found.');
				return;
			}

			if (tagsToRemove.length === 0 || tagsToRemove[0] === '') {
				alert('Please enter some tags to remove.');
				return;
			}

			// Select only the checked checkboxes within #edd-upload-tbody, excluding #actionSelectAll
			const selectedCheckboxes = document.querySelectorAll('#edd-upload-tbody .bulkActionSelections:checked:not(#actionSelectAll)');
			console.log('Selected checkboxes for tag removal:', selectedCheckboxes);

			if (selectedCheckboxes.length === 0) {
				alert('No items selected for tag removal.');
				return;
			}

			selectedCheckboxes.forEach(function (checkbox) {
				const checkboxId = checkbox.id; // e.g., 'actionupload-1734050304203-343'
				const rowId = checkboxId.replace(/^action/, ''); // 'upload-1734050304203-343'
				const row = document.getElementById(rowId);

				if (row) {
					const tagsInput = row.querySelector('.tags-input');
					if (tagsInput) {
						const existingTags = tagsInput.value.split(',').map(tag => tag.trim());
						const updatedTags = existingTags.filter(tag => !tagsToRemove.includes(tag));
						tagsInput.value = updatedTags.join(', ');
						console.log(`Tags updated for row ID: ${rowId}`);
					} else {
						console.warn(`.tags-input not found in row ID: ${rowId}`);
					}
				} else {
					console.warn(`Row with ID '${rowId}' not found.`);
				}
			});

			removeTagsInput.value = '';

			alert('Tags removed from selected uploads!');
		});
	}

	if (addCategoryButton) {
		addCategoryButton.addEventListener('click', function () {
			const newCategoryInput = document.getElementById('categoryChangeinput');
			const newCategory = newCategoryInput ? newCategoryInput.value.trim() : '';

			if (!newCategoryInput) {
				console.error('Category input element not found.');
				return;
			}

			if (newCategory === '') {
				alert('Please enter some categories to add.');
				return;
			}

			// Select only the checked checkboxes within #edd-upload-tbody, excluding #actionSelectAll
			const selectedCheckboxes = document.querySelectorAll('#edd-upload-tbody .bulkActionSelections:checked:not(#actionSelectAll)');
			console.log('Selected checkboxes for category addition:', selectedCheckboxes);

			if (selectedCheckboxes.length === 0) {
				alert('No items selected for category addition.');
				return;
			}

			selectedCheckboxes.forEach(function (checkbox) {
				const checkboxId = checkbox.id; // e.g., 'actionupload-1734050304203-343'
				const rowId = checkboxId.replace(/^action/, ''); // 'upload-1734050304203-343'
				const row = document.getElementById(rowId);

				if (row) {
					const categoryInput = row.querySelector('.category-input');
					if (categoryInput) {
						const existingCategory = categoryInput.value.trim();
						if (existingCategory) {
							categoryInput.value = `${existingCategory}, ${newCategory}`;
						} else {
							categoryInput.value = newCategory;
						}
						console.log(`Category updated for row ID: ${rowId}`);
					} else {
						console.warn(`.category-input not found in row ID: ${rowId}`);
					}
				} else {
					console.warn(`Row with ID '${rowId}' not found.`);
				}
			});

			// Clear the form input after adding categories
			newCategoryInput.value = '';
			alert('Categories added to selected uploads!');
		});
	}


	if (removeCategoryButton) {
		removeCategoryButton.addEventListener('click', function () {
			const removeCategoryInput = document.getElementById('categoryChangeinput');
			const categoriesToRemove = removeCategoryInput ? removeCategoryInput.value.split(',').map(category => category.trim()) : [];

			if (!removeCategoryInput) {
				console.error('Category input element not found.');
				return;
			}

			if (categoriesToRemove.length === 0 || categoriesToRemove[0] === '') {
				alert('Please enter some categories to remove.');
				return;
			}

			// Select only the checked checkboxes within #edd-upload-tbody, excluding #actionSelectAll
			const selectedCheckboxes = document.querySelectorAll('#edd-upload-tbody .bulkActionSelections:checked:not(#actionSelectAll)');
			console.log('Selected checkboxes for category removal:', selectedCheckboxes);

			if (selectedCheckboxes.length === 0) {
				alert('No items selected for category removal.');
				return;
			}

			selectedCheckboxes.forEach(function (checkbox) {
				const checkboxId = checkbox.id; // e.g., 'actionupload-1734050304203-343'
				const rowId = checkboxId.replace(/^action/, ''); // 'upload-1734050304203-343'
				const row = document.getElementById(rowId);

				if (row) {
					const categoryInput = row.querySelector('.category-input');
					if (categoryInput) {
						const existingCategories = categoryInput.value.split(',').map(category => category.trim());
						const updatedCategories = existingCategories.filter(category => !categoriesToRemove.includes(category));
						categoryInput.value = updatedCategories.join(', ');
						console.log(`Categories updated for row ID: ${rowId}`);
					} else {
						console.warn(`.category-input not found in row ID: ${rowId}`);
					}
				} else {
					console.warn(`Row with ID '${rowId}' not found.`);
				}
			});

			// Clear the form input after removing categories
			removeCategoryInput.value = '';
			alert('Categories removed from selected uploads!');
		});
	}



	if (bulkUpdatePricesButton) {
		bulkUpdatePricesButton.addEventListener('click', function () {
			const bulkUpdatePricesInput = document.getElementById('bulk-update-prices-input');
			const newPrice = bulkUpdatePricesInput ? parseFloat(bulkUpdatePricesInput.value.trim()) : NaN;

			if (!bulkUpdatePricesInput) {
				console.error('Bulk update prices input element not found.');
				return;
			}

			if (isNaN(newPrice) || newPrice < 0) {
				alert('Please enter a valid positive number for the price.');
				console.warn('Invalid price entered'); 
				return;
			}

			// Select only the checked checkboxes within #edd-upload-tbody, excluding #actionSelectAll
			const selectedCheckboxes = document.querySelectorAll('#edd-upload-tbody .bulkActionSelections:checked:not(#actionSelectAll)');
			console.log('Selected checkboxes for price update:', selectedCheckboxes);

			if (selectedCheckboxes.length === 0) {
				alert('No items selected for price update.');
				return;
			}

			selectedCheckboxes.forEach(function (checkbox) {
				const checkboxId = checkbox.id; // e.g., 'actionupload-1734050304203-343'
				const rowId = checkboxId.replace(/^action/, ''); // 'upload-1734050304203-343'
				const row = document.getElementById(rowId);

				if (row) {
					const priceInput = row.querySelector('.price-input');
					if (priceInput) {
						priceInput.value = newPrice.toFixed(2);
						console.log(`Price updated for row ID: ${rowId}`);
					} else {
						console.warn(`.price-input not found in row ID: ${rowId}`);
					}
				} else {
					console.warn(`Row with ID '${rowId}' not found.`);
				}
			});

			// Clear the form input after updating prices
			bulkUpdatePricesInput.value = '';

			alert('Prices updated for selected uploads!');
		});
	}

	
	function edd_bulk_lite_upload_toggleGenerateTagsButtonState() {
		if (pendingUploads > 0 || pendingProcesses > 0) {
			generateImportFileButton.prop("disabled", true);
		} else {
			generateImportFileButton.prop("disabled", false);
		}
	}
    

    function edd_bulk_lite_upload_InitiateS3Upload(originalUrl, newFileName, $currentRow,renamedUrl,fileType,uniqueId,watermarkedUrl) {
        if (edd_bulk_lite_upload_vars.edd_bulk_lite_enable_s3_storage === 'on') {
			pendingProcesses ++;
			edd_bulk_lite_upload_toggleGenerateTagsButtonState()
            $('#edd-generate-import-file').prop('disabled', true);
            $currentRow.find('.upload-status').text('Uploading to S3...').css('color', 'orange');
            if (edd_bulk_lite_upload_vars.js_debugging === "1") {
                console.log("S3 storage enabled. Initiating S3 upload for:", newFileName);
                console.log("Renamed File Name:", newFileName);
                console.log("Renamed URL:", renamedUrl);
                console.log("Original URL:", originalUrl);
            }
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'edd_bulk_lite_upload_send_file_to_amazon_s3',
                    edd_bulk_lite_upload_nonce: edd_bulk_lite_upload_vars.nonce,
                    download_url: originalUrl,
                    file_name: newFileName,
                    file_type: fileType,
                },
                success: function (s3Response) {
					pendingProcesses --;
					edd_bulk_lite_upload_toggleGenerateTagsButtonState()
                    if (edd_bulk_lite_upload_vars.js_debugging === "1") {
                        console.log("S3 upload response:", s3Response);
                    }
                    if (s3Response.success) {
                        let s3Url = s3Response.data.s3_url.split('?')[0]; // Strip auth string from S3 URL
                        $currentRow.find('.upload-status').text('Uploaded to S3').css('color', 'green');

                        if (fileType.startsWith('image/')) {
                            // For images, update the download URL to S3 URL, keep upload URL as scaled
                            $currentRow.find('.download-url').html(`<a href="${s3Url}" target="_blank">${s3Url}</a>`);
                        } else if (fileType.startsWith('video/')) {
                            // For videos, update both upload and download URLs to S3 URL
                            $currentRow.find('.upload-url').html(`<a href="${originalUrl}" target="_blank">${originalUrl}</a>`);
                            $currentRow.find('.download-url').html(`<a href="${s3Url}" target="_blank">${s3Url}</a>`);
                        }

                        // Update uploadedFiles with the correct URLs
                        uploadedFiles.push({
                            id: uniqueId,
                            title: $currentRow.find('.title-input').val(),
                            upload_url: fileType.startsWith('video/') ? s3Url : watermarkedUrl,
                            download_url: s3Url,
                            tags: $currentRow.find('.tags-input').val(),
                            category: $currentRow.find('.category-input').val(),
                            content: $currentRow.find('.content-input').val() || '',
                            item_price: $currentRow.find('.price-input').val(),
                        });
                        edd_bulk_lite_upload_toggleGenerateTagsButtonState();
                    } else {
                        $currentRow.find('.upload-status').text('Failed to upload to S3').css('color', 'red');
                        if (edd_bulk_lite_upload_vars.js_debugging === "1") {
                            console.error("S3 upload failed for file:", file.name, s3Response);
                        }
                        
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
					pendingProcesses --;
					edd_bulk_lite_upload_toggleGenerateTagsButtonState()
                    $currentRow.find('.upload-status').text('Failed to upload to S3').css('color', 'red');
                    if (edd_bulk_lite_upload_vars.js_debugging === "1") {
                        console.error("S3 AJAX error for file:", file.name, textStatus, errorThrown);
                    }
                    
                },
            });
        } else {
            uploadedFiles.push({
                id: uniqueId,
                title: $currentRow.find('.title-input').val(),
                upload_url: watermarkedUrl,
                download_url: originalUrl,
                tags: $currentRow.find('.tags-input').val(),
                category: $currentRow.find('.category-input').val(),
                content: $currentRow.find('.content-input').val() || '',
                item_price: $currentRow.find('.price-input').val(),
            });
        }
    }

	function edd_bulk_lite_upload_handleUploadCompletion() {
		pendingUploads--;
		edd_bulk_lite_upload_toggleGenerateTagsButtonState();
		if (pendingUploads === 0 && uploadedFiles.length > 0 && pendingProcesses === 0 ) {
			$('#edd-generate-import-file').prop('disabled', false);
		}
	}

	function edd_bulk_lite_upload_handleUploadStart() {
		pendingUploads++;
		edd_bulk_lite_upload_toggleGenerateTagsButtonState();
		$('#edd-generate-import-file').prop('disabled', true);
	}

	function startNextUpload() {
		while (activeUploads < maxSimultaneous && uploadQueue.length > 0  && pendingProcesses < maxSimultaneous) {
			var nextItem = uploadQueue.shift();
			processFileUpload(
				nextItem.file,
				nextItem.uniqueId,
				nextItem.$row,
				nextItem.formData,
				nextItem.newFileName,
				nextItem.renamedFile
			);
		}
	}

	function processFileUpload(file, uniqueId, $row, formData, newFileName, renamedFile) {
		activeUploads++;
		edd_bulk_lite_upload_handleUploadStart();

		// Update row status from 'In Queue' to 'Uploading...'
		$row.find('.upload-status').text('Uploading...').css('color', 'blue');
		$row.find('.thumbnail-column').html(`<span>Uploading...</span>`).css('color', 'blue');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			xhr: function () {
				let xhr = new window.XMLHttpRequest();
				xhr.upload.addEventListener('progress', function (event) {
					if (event.lengthComputable) {
						let percentComplete = (event.loaded / event.total) * 100;
						$row.find('.upload-progress').val(percentComplete);
						if (edd_bulk_lite_upload_vars.js_debugging === "1") {
							console.log(`Upload progress for ${newFileName}: ${percentComplete}%`);
						}
					}
				}, false);
				return xhr;
			},
			success: function (response) {
				if (edd_bulk_lite_upload_vars.js_debugging === "1") {
					console.log("AJAX success response:", response);
				}

				if (response.success && response.data.files.length > 0) {
					let fileData = response.data.files[0];
					if (edd_bulk_lite_upload_vars.js_debugging === "1") {
						console.log("File data received:", fileData);
					}

					if (fileData && fileData.original) {
						let $currentRow = pendingUploadsMap.get(uniqueId).$row;
						const originalUrl = fileData.original.split('?')[0];
						const renamedUrl = originalUrl.replace(/[^/]+$/, renamedFile);
						const baseDir = originalUrl.substring(0, originalUrl.lastIndexOf('/'));
						let scaledUrl = fileData.scaled.replace(/^.*[\\/]/, `${baseDir}/`);
						let watermarkedUrl = fileData.scaled.replace(/^.*[\\/]/, `${baseDir}/`);
						const fileType = file.type;

						if (fileType.startsWith('image/')) {
							let thumbnailUrl = fileData.thumbnail.replace(/^.*[\\/]/, `${baseDir}/`);
							scaledUrl = fileData.scaled.replace(/^.*[\\/]/, `${baseDir}/`);
							$currentRow.find('.thumbnail-column').html(`<img src="${thumbnailUrl}" alt="Thumbnail" style="width: 150px; height: auto;">`);
							if (edd_bulk_lite_upload_vars.js_debugging === "1") {
								console.log("Watermarking disabled, proceeding directly to check S3 settings");
							}
							// Call S3 upload function as-is
							edd_bulk_lite_upload_InitiateS3Upload(originalUrl, newFileName, $currentRow, renamedUrl, fileType, uniqueId, watermarkedUrl);
							$currentRow.find('.upload-url').html(`<a href="${scaledUrl}" target="_blank">${scaledUrl}</a>`);
							$currentRow.find('.download-url').html(`<a href="${originalUrl}" target="_blank">${originalUrl}</a>`);
							$currentRow.find('.category-input').val('Images');

						} else if (fileType.startsWith('video/')) {
							let videoPlaceholder = String(edd_bulk_lite_upload_vars.pluginUrl) + 'assets/images/video-placeholder.png';
							if (edd_bulk_lite_upload_vars.js_debugging === "1") {
								console.log("Video Placeholder Filepath:", videoPlaceholder);
							}
							scaledUrl = originalUrl;
							$currentRow.find('.upload-url').html(`<a href="${scaledUrl}" target="_blank">${scaledUrl}</a>`);
							$currentRow.find('.thumbnail-column').html(
								`<img src="${videoPlaceholder}" alt="Video Placeholder" style="width: 150px; height: auto;">`
							);
							$currentRow.find('.category-input').val('Videos');
							// Call S3 upload function as-is
							edd_bulk_lite_upload_InitiateS3Upload(originalUrl, newFileName, $currentRow, renamedUrl, fileType, uniqueId, watermarkedUrl);
						}

						$currentRow.find('.upload-status').text('Uploaded').css('color', 'green');
						$currentRow.find('.download-url').html(`<a href="${originalUrl}" target="_blank">${originalUrl}</a>`);
						if (edd_bulk_lite_upload_vars.js_debugging === "1") {
							console.log("File added to uploadedFiles array:", fileData.original);
						}
						uploadedFiles.push(fileData.original);
					} else {
						if (edd_bulk_lite_upload_vars.js_debugging === "1") {
							console.error("File URL not retrieved for file:", file.name);
						}
						let $currentRow = pendingUploadsMap.get(uniqueId).$row;
						$currentRow.find('.upload-status').text('URL not retrieved').css('color', 'red');
					}
				}
			},
			error: function (jqXHR, textStatus, errorThrown) {
				if (edd_bulk_lite_upload_vars.js_debugging === "1") {
					console.error("AJAX error for file:", file.name, textStatus, errorThrown);
				}
				let $currentRow = pendingUploadsMap.get(uniqueId).$row;
				$currentRow.find('.upload-status').text('Failed').css('color', 'red');
			},
			complete: function () {
				edd_bulk_lite_upload_handleUploadCompletion();
				pendingUploadsMap.delete(uniqueId);
				activeUploads--;

				if (pendingUploadsMap.size === 0 && uploadedFiles.length > 0) {
					if (edd_bulk_lite_upload_vars.js_debugging === "1") {
						console.log('All uploads completed. Buttons are now enabled.');
					}
					$('#edd-generate-import-file').prop('disabled', false);
				}

				// Attempt to start another upload
				startNextUpload();
			}
		});
	}

	// Main function to handle file uploads with queuing
	function edd_bulk_lite_upload_handleFileUploads(files) {
		if (edd_bulk_lite_upload_vars.js_debugging === "1") {
			console.log("edd_bulk_lite_upload_vars: ", edd_bulk_lite_upload_vars);
			console.log("Starting file upload process...", files);
		}

		// Disable the import file button while processing
		$('#edd-generate-import-file').prop('disabled', true);

		$.each(files, function (index, file) {
			if (!file.type.startsWith('image/') && !file.type.startsWith('video/')) {
				if (edd_bulk_lite_upload_vars.js_debugging === "1") {
					console.error("Invalid file type for file:", file.name);
				}
				alert(`Invalid file type: ${file.name}. Only images and videos are allowed.`);
				return true;
			}
			if (edd_bulk_lite_upload_vars.js_debugging === "1") {
				console.log(`Processing file ${index + 1} of ${files.length}:`, file);
				console.log(edd_bulk_lite_upload_vars);
			}

			let formData = new FormData();
			let newFileName = file.name;
			let renamedFile = new File([file], newFileName, { type: file.type });

			// Generate unique ID
			let uniqueId = `upload-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
			if (edd_bulk_lite_upload_vars.js_debugging === "1") {
				console.log(`Generated unique ID: ${uniqueId}`);
			}

			const processedFileName = (newFileName || '').toString().replace(/\./g, '_'); 
			let $row = $('<tr>')
				.attr('data-upload-id', uniqueId)
				.attr('id',uniqueId)
				.append($('<td>').html(`<div class="action-container"><input type="checkbox" name="action${uniqueId}" id="action${uniqueId}" class="bulkActionSelections"></div>`))
				.append($('<td class="thumbnail-column">').html('<span>In Queue...</span>')) // Indicate "In Queue" here
				.append($('<td>').html(`<h5>Title</h5><input type="text" class="title-input" value="${processedFileName}" /><br/>
										<h5>Content (Description)</h5><textarea rows="7" cols="50" class="content-input" placeholder="Add content description..."></textarea>`)) 
				.append($('<td>').html(`<h5>Tags</h5><textarea rows="3" cols="50" class="tags-input" placeholder="Add tags, (comma-separated)"></textarea><br/>
										<h5>Categories</h5><input type="text" class="category-input" placeholder="Add categories, (comma-separated)" /><br/>
										<h5>Price</h5><input type="text" class="price-input" placeholder="Enter price" />`))
				.append($('<td class="file-url">').html(
					'<div><span>Upload URL: <span class="upload-url">URL not available yet</span></span></div>' +
					'<div><span>Download URL: <span class="download-url">URL not available yet</span></span></div>'
				))
				.append($('<td>').html('<span class="upload-status">In Queue</span><progress value="0" max="100" class="upload-progress"></progress>')); // Show "In Queue" here

			$('#edd-upload-tbody').append($row);
			if (edd_bulk_lite_upload_vars.js_debugging === "1") {
				console.log("Added new row for file:", file.name);
			}

			pendingUploadsMap.set(uniqueId, {
				file: file,
				$row: $row,
			});

			formData.append('edd_files[]', renamedFile); 
			formData.append('action', 'edd_bulk_lite_upload_handle_file_uploads');
			formData.append('edd_bulk_lite_upload_nonce', edd_bulk_lite_upload_vars.nonce);

			if (edd_bulk_lite_upload_vars.js_debugging === "1") {
				console.log("Inspecting FormData before queuing:");
				for (let pair of formData.entries()) {
					console.log(`${pair[0]}: ${pair[1]}`);
				}
			}

			// Add to upload queue instead of sending immediately
			uploadQueue.push({
				file: file,
				uniqueId: uniqueId,
				$row: $row,
				formData: formData,
				newFileName: newFileName,
				renamedFile: renamedFile
			});
		});

		// Try to start some uploads
		startNextUpload();
	}
    
    $('#edd-generate-import-file').on('click', function() {
        uploadedFiles = [];
        $('#edd-upload-tbody tr').each(function() {
            let $row = $(this);
            let fileUrl = $row.find('.upload-url a').attr('href');
            let downloadUrl = $row.find('.download-url a').attr('href');
            let price = $row.find('.price-input').val();
            let title = $row.find('.title-input').val();
            let tags = $row.find('.tags-input').val();
            let category = $row.find('.category-input').val();
            let content = $row.find('.content-input').val();
            if (fileUrl && downloadUrl) {
                uploadedFiles.push({
                    title: title || '',
                    upload_url: fileUrl,
                    download_url: downloadUrl,
                    tags: tags || '',
                    category: category || '',
                    content: content || '',
                    item_price: price || '0',
                });
            }
        });

        if (uploadedFiles.length === 0) {
            alert('No files uploaded for bulk import.');
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'edd_bulk_lite_upload_generate_csv',
                edd_bulk_lite_upload_nonce: edd_bulk_lite_upload_vars.nonce,
                files: uploadedFiles, 
            },
            success: function(response) {
                if (response.success) {
                    edd_bulk_lite_upload_showCSVActionArea(response.data.csv_file);
                } else {
                    alert('Failed to generate CSV: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while generating the CSV.');
            }
        });
    });

    function edd_bulk_lite_upload_showCSVActionArea(csvFilePath) {
        $('#edd-csv-action-content').remove();
        $('#edd-generate-import-file').text('Regenerate CSV');
        $('#generate-import-button-area').append(`
            <div id="edd-csv-action-content" class="edd-csv-action-content">
                <h2>CSV Generated Successfully</h2>
                <p>The CSV for bulk imports has been successfully generated. You can download and review it, or proceed to import it directly.</p>
                <div class="edd-action-buttons">
                    <button id="edd-proceed-to-import" class="button button-secondary">Proceed to Import</button>
                    <p>CSV URL: <span id="csv-url">${csvFilePath}</span> <button id="copy-csv-url" class="button">Copy URL</button></p>
                </div>
            </div>
        `);

        $('#copy-csv-url').on('click', function() {
            let csvUrl = $('#csv-url').text();
            navigator.clipboard.writeText(csvUrl).then(function() {
                alert('CSV URL copied to clipboard.');
            }, function() {
                alert('Failed to copy CSV URL.');
            });
        });

        $('#edd-proceed-to-import').on('click', function () {
            if (confirm('All items in the CSV will now be imported as new items. Do you want to proceed?')) {
                const csvFilePath = $('#csv-url').text();
                const chunkSize = 5; 
                let offset = 0;
                let totalItems = 0;

                edd_bulk_lite_upload_showLoadingModal('Calculating total items...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'edd_bulk_lite_upload_count_csv_rows',
                        edd_bulk_lite_upload_nonce: edd_bulk_lite_upload_vars.nonce,
                        csv_file_path: csvFilePath,
                    },
                    success: function (response) {
                        if (response.success) {
                            totalItems = response.data.total_rows; // Update total count
                            edd_bulk_lite_upload_updateLoadingModal(0, totalItems); // Initialize progress display
                            edd_bulk_lite_upload_processNextChunk(); // Start the import process
                        } else {
                            alert('Failed to count rows: ' + response.data.message);
                            hideLoadingModal();
                        }
                    },
                    error: function () {
                        alert('An error occurred while counting rows.');
                        hideLoadingModal();
                    },
                });

                function edd_bulk_lite_upload_processNextChunk() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'edd_bulk_lite_upload_confirm_import',
                            edd_bulk_lite_upload_nonce: edd_bulk_lite_upload_vars.nonce,
                            csv_file_path: csvFilePath,
                            offset: offset,
                            chunk_size: chunkSize,
                        },
                        success: function (response) {
                            if (response.success) {
                                offset = response.data.offset;
                                const currentItem = totalItems - response.data.remaining;
                                edd_bulk_lite_upload_updateLoadingModal(currentItem, totalItems);

                                if (response.data.completed) {
                                    $('#loading-modal-message').text('Import completed successfully.');
                                    setTimeout(() => {
                                        hideLoadingModal();
                                        window.location.href = response.data.redirect_url;
                                    }, 1000); 
                                } else {
                                    edd_bulk_lite_upload_processNextChunk(); 
                                }
                            } else {
                                if (edd_bulk_lite_upload_vars.js_debugging === 'on') {
                                    console.error('Import failed for current chunk:', response.data.message);
                                }
                                alert('Failed to import: ' + response.data.message);
                                hideLoadingModal();
                            }
                        },
                        error: function () {
                            alert('An error occurred while processing the import.');
                            hideLoadingModal();
                        },
                    });
                }

                // Show loading modal
                function edd_bulk_lite_upload_showLoadingModal(initialMessage) {
                    let modalHtml = `
                        <div id="edd-loading-modal" class="edd-loading-modal">
                            <div class="edd-modal-content">
                                <img style="display:block;width:100px;margin:auto;" src="/wp-content/plugins/edd-bulk-uploader-importer-lite/assets/images/spinner.gif" alt="Processing...."/>
                                <h2>Import in Progress</h2>
                                <p id="loading-modal-message">${initialMessage}</p>
                                <p>This process can take a long time when performing large batches depending on your hosting environments performance.</p>  
                                <p>Please do not close this window or navigate away.  Progress will update and continue in batches of 5 until completion to avoid timeouts.</p>
                                <p>You will be redirected to the Downloads page on completion.</p>
                            </div>
                        </div>
                    `;
                    $('body').append(modalHtml);
                }

                function edd_bulk_lite_upload_updateLoadingModal(currentItem, totalItems) {
                    const startItem = currentItem + 1;
                    const endItem = Math.min(startItem + 4, totalItems);
                    $('#current-item').text(startItem);
                    $('#total-items').text(totalItems);
                    $('#loading-modal-message').text(`Processing items ${startItem} - ${endItem} of ${totalItems}...`);
                }

                function hideLoadingModal() {
                    $('#edd-loading-modal').remove();
                }
            }
        });


       
    }
  
	$('#actionSelectAll').on('change', function(){
		const isChecked = $(this).is(':checked');

		$('.bulkActionSelections').not('#actionSelectAll').prop('checked', isChecked);
	});
  
});
