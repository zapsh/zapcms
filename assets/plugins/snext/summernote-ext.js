// Author: ZAP
// SN Finder Summernote Plugin
(function(factory) {
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define(['jquery'], factory);
  } else if (typeof module === 'object' && module.exports) {
    // Node/CommonJS
    module.exports = factory(require('jquery'));
  } else {
    // Browser globals
    factory(window.jQuery);
  }
}(function($) {


  $.extend($.summernote.plugins, {
    /**
     * @param {Object} context - context object has status of editor.
     */
    'snfinder': function(context) {
      var self = this;


      var ui = $.summernote.ui;

      context.memo('button.snfinder', function() {
        var button = ui.button({
          contents: '<i class="fa fa-image"/> 图片',
          tooltip: 'ZAP 文件管理器',
          container: '.note-editor.note-frame',
          click: function(e) {
            Zap.finder({callback:'insertEditor',context:context});
            // context.invoke('editor.insertText', 'hello');
          },
        });

        var $snfinder = button.render();
        return $snfinder;
      });

      // This events will be attached when editor is initialized.
      this.events = {
      };

      this.initialize = function() {

      };

      this.destroy = function() {

      };
    },
  });
}));
function insertEditor(e){
  window.zapFinder.settings.context.invoke('editor.insertImage', $(e.target).data('original') , $(e.target).attr('alt'));
}

// summernote-image-attributes.js

/* https://github.com/adeelhussain/summernote-image-attribute-editor */
(function (factory) {

	if (typeof define === 'function' && define.amd) {
		define(['jquery'], factory)
	} else if (typeof module === 'object' && module.exports) {
		module.exports = factory(require('jquery'));
	} else {
		factory(window.jQuery)
	}
}
	(function ($) {
		// 补齐 imageAttributes 插件语言包（原插件只定义了 en-US 且不完整，
		// 在 zh-CN 下 widthLabel/heightLabel 等全为 undefined）。
		$.extend(true, $.summernote.lang, {
			'en-US': {
				imageAttributes: {
					edit: 'Edit Attributes',
					titleLabel: 'Title',
					altLabel: 'Alternative Text',
					captionLabel: 'Caption',
					widthLabel: 'Width',
					heightLabel: 'Height',
					imageLockLabel: 'Lock',
					resizeLabel: 'Reset size',
					alignLabel: 'Alignment',
					alignNone: 'None',
					alignLeft: 'Left',
					alignCenter: 'Center',
					alignRight: 'Right',
					classLabel: 'CSS Class',
					idLabel: 'ID',
					hrefLabel: 'Link URL',
					targetLabel: 'Open In',
					targetSelf: 'Same window',
					targetBlank: 'New window',
					tooltip: 'Edit Image',
					dialogSaveBtnMessage: 'Save',
					dialogTitle: 'Change Image Attributes'
				}
			},
			'zh-CN': {
				imageAttributes: {
					edit: '编辑属性',
					titleLabel: '标题',
					altLabel: '替代文本',
					captionLabel: '图片说明',
					widthLabel: '宽度',
					heightLabel: '高度',
					imageLockLabel: '锁定宽高比',
					resizeLabel: '重置尺寸',
					alignLabel: '对齐方式',
					alignNone: '默认',
					alignLeft: '左对齐',
					alignCenter: '居中',
					alignRight: '右对齐',
					classLabel: 'CSS 类',
					idLabel: 'ID',
					hrefLabel: '点击链接',
					targetLabel: '打开方式',
					targetSelf: '当前窗口',
					targetBlank: '新窗口',
					tooltip: '编辑图片属性',
					dialogSaveBtnMessage: '保存',
					dialogTitle: '修改图片属性'
				}
			}
		});
		$.extend($.summernote.options, {
			imageAttributes: {
				icon: '<i class="note-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 14 14" width="14" height="14"><path d="M 8.781,11.11 7,11.469 7.3595,9.688 8.781,11.11 Z M 7.713,9.334 9.135,10.7565 13,6.8915 11.5775,5.469 7.713,9.334 Z M 6.258,9.5 8.513,7.12 7.5,5.5 6.24,7.5 5,6.52 3,9.5 6.258,9.5 Z M 4.5,5.25 C 4.5,4.836 4.164,4.5 3.75,4.5 3.336,4.5 3,4.836 3,5.25 3,5.6645 3.336,6 3.75,6 4.164,6 4.5,5.6645 4.5,5.25 Z m 1.676,5.25 -4.176,0 0,-7 9,0 0,1.156 1,0 0,-2.156 -11,0 0,9 4.9845,0 0.1915,-1 z"/></svg></i>',
				figureClass: '',
				figcaptionClass: '',
				captionText: 'Caption Goes Here.'
			}
		});
		$.extend($.summernote.plugins, {
			'imageAttributes': function (context) {
				var self = this;
				var ui = $.summernote.ui,
					$editable = context.layoutInfo.editable,
					options = context.options,
					$editor = context.layoutInfo.editor,
					lang = options.langInfo,
					$note = context.layoutInfo.note;

				context.memo('button.imageAttributes', function () {
					var button = ui.button({
						contents: options.imageAttributes.icon,
						container: false,
						tooltip: lang.imageAttributes.tooltip,
						click: function () {
							context.invoke('imageAttributes.show');
						}
					});
					return button.render();
				});

				this.initialize = function () {
					// Either the modal appends in Body or Inside the Editor
					var $container = options.imageAttributes.dialogsInBody ? $(document.body) : $editor;

					var body = ` <div class="form-group">
									<label class="note-form-label">${lang.imageAttributes.titleLabel}</label>
									<input class="form-control note-input note-image-title-text" type="text" />
								</div>
								<div class="form-group">
									<label class="note-form-label">${lang.imageAttributes.altLabel}</label>
									<input class="form-control note-input note-image-alt-text" type="text" />
								</div>
								<div class							<div class="form-group">
									<label class="note-form-label">${lang.imageAttributes.captionLabel}</label>
									<input class="form-control note-input note-image-caption-text" type="text" />
									</div>
									<div class="row">
									<div class="form-group col-sm-6">
										<label class="note-form-label">${lang.imageAttributes.alignLabel}</label>
										<select class="form-control note-input note-image-align">
											<option value="">${lang.imageAttributes.alignNone}</option>
											<option value="left">${lang.imageAttributes.alignLeft}</option>
											<option value="center">${lang.imageAttributes.alignCenter}</option>
											<option value="right">${lang.imageAttributes.alignRight}</option>
										</select>
									</div>
									<div class="form-group col-sm-6">
										<label class="note-form-label">${lang.imageAttributes.classLabel}</label>
										<input class="form-control note-input note-image-class" type="text" />
									</div>
									</div>
									<div class="row">
									<div class="form-group col-sm-8">
										<label class="note-form-label">${lang.imageAttributes.hrefLabel}</label>
										<input class="form-control note-input note-image-href" type="text" placeholder="https://" />
									</div>
									<div class="form-group col-sm-4">
										<label class="note-form-label">${lang.imageAttributes.targetLabel}</label>
										<select class="form-control note-input note-image-target">
											<option value="">${lang.imageAttributes.targetSelf}</option>
											<option value="_blank">${lang.imageAttributes.targetBlank}</option>
										</select>
									</div>
									</div>
									<div class="form-group">
									<label class="note-form-label">${lang.imageAttributes.idLabel}</label>
									<input class="form-control note-input note-image-id" type="text" />
									</div>
									<div class="row">
									<div class="form-group col-sm-4">
										<label class="note-form-label">${lang.imageAttributes.widthLabel}</label>
										<input class="form-control note-input note-image-width" type="number" />
									</div>
									<div class="form-group col-sm-4">
										<label class="note-form-label">${lang.imageAttributes.heightLabel}</label>
										<input class="form-control note-input note-image-height" type="number" />
									</div>
									<div class="form-group col-sm-4">
										<label class="note-form-label">${lang.imageAttributes.imageLockLabel}</label>
										<div>
											<button type="button" class="btn btn-outline-secondary btn-sm lock-button" title="${lang.imageAttributes.imageLockLabel}">
												<span><i class="note-icon icon-lock"><svg xmlns="http://www.w3.org/2000/svg" width="14px" height="13.600px" viewBox="0 0 1000 1000" enable-background="new 0 0 1000 1000" xml:space="preserve"> <g><path d="M321.8,455.5h356.4V321.8c0-49.2-17.4-91.2-52.2-126c-34.8-34.8-76.8-52.2-126-52.2c-49.2,0-91.2,17.4-126,52.2c-34.8,34.8-52.2,76.8-52.2,126L321.8,455.5L321.8,455.5z M900.9,522.3v400.9c0,18.6-6.5,34.3-19.5,47.3c-13,13-28.8,19.5-47.3,19.5H165.9c-18.6,0-34.3-6.5-47.3-19.5c-13-13-19.5-28.8-19.5-47.3V522.3c0-18.6,6.5-34.3,19.5-47.3c13-13,28.8-19.5,47.3-19.5h22.3V321.8c0-85.4,30.6-158.7,91.9-219.9C341.3,40.7,414.7,10,500,10c85.3,0,158.7,30.6,219.9,91.9c61.3,61.3,91.9,134.6,91.9,219.9v133.6h22.3c18.6,0,34.3,6.5,47.3,19.5C894.4,488,900.9,503.7,900.9,522.3L900.9,522.3z"/></g></svg></i></span>
												<span><i class="note-icon icon-unlock" style="display:none;"><svg xmlns="http://www.w3.org/2000/svg" width="14px" height="13.600px" viewBox="0 0 438.533 438.533" style="width: 13px;enable-background:new 0 0 438.533 438.533;" xml:space="preserve"><g><path d="M375.721,227.259c-5.331-5.331-11.8-7.992-19.417-7.992H146.176v-91.36c0-20.179,7.139-37.402,21.415-51.678   c14.277-14.273,31.501-21.411,51.678-21.411c20.175,0,37.402,7.137,51.673,21.411c14.277,14.276,21.416,31.5,21.416,51.678   c0,4.947,1.807,9.229,5.42,12.845c3.621,3.617,7.905,5.426,12.847,5.426h18.281c4.945,0,9.227-1.809,12.848-5.426   c3.606-3.616,5.42-7.898,5.42-12.845c0-35.216-12.515-65.331-37.541-90.362C284.603,12.513,254.48,0,219.269,0   c-35.214,0-65.334,12.513-90.366,37.544c-25.028,25.028-37.542,55.146-37.542,90.362v91.36h-9.135   c-7.611,0-14.084,2.667-19.414,7.992c-5.33,5.325-7.994,11.8-7.994,19.414v164.452c0,7.617,2.665,14.089,7.994,19.417   c5.33,5.325,11.803,7.991,19.414,7.991h274.078c7.617,0,14.092-2.666,19.417-7.991c5.325-5.328,7.994-11.8,7.994-19.417V246.673   C383.719,239.059,381.053,232.591,375.721,227.259z"/></g></svg></i></span>
											</button>
											<button type="button" class="btn btn-outline-secondary btn-sm reset-size-buttton" aria-label="${lang.imageAttributes.resizeLabel}" title="${lang.imageAttributes.resizeLabel}">
												<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/></svg>
											</button>
										</div>
									</div>
								</div>`;

					var footer = `<button href="#" class="btn btn-primary note-image-title-btn note-btn">${lang.imageAttributes.dialogSaveBtnMessage}</button>`;

					this.$dialog = ui.dialog({
						title: lang.imageAttributes.dialogTitle,
						body: body,
						footer: footer
					}).render().appendTo($container);

				};

				this.destroy = function () {
					ui.hideDialog(this.$dialog);
					this.$dialog.remove();
				};

				this.bindEnterKey = function ($input, $btn) {
					$input.on('keypress', function (event) {
						if (event.keyCode === 13) {
							$btn.trigger('click');
						}
					});
				};

				this.show = function () {
					var $img = $($editable.data('target'));
					var $link = $img.closest('a');
					var _imgInfo = {
						title: $img.attr('title'),
						alt: $img.attr('alt'),
						caption: $img.next('figcaption').text(),
						width: $img.width(),
						height: $img.height(),
						align: $img.data('align') || ($img.css('float') === 'left' ? 'left' : ($img.css('float') === 'right' ? 'right' : (($img.css('margin-left') === 'auto' && $img.css('margin-right') === 'auto') ? 'center' : ''))),
						className: $img.attr('class') || '',
						id: $img.attr('id') || '',
						href: $link.attr('href') || '',
						target: $link.attr('target') || ''
					};

					var img = new Image();
					img.onload = function () {
						_imgInfo.naturalWidth = img.width
						_imgInfo.naturalHeight = img.height;
					}
					img.src = $img.attr('src');
					

					this.showLinkDialog(_imgInfo)
						.then(function (imgInfo) {
							ui.hideDialog(self.$dialog);
							var isAnyChangeMade = false;

							// NOTE: Must add more conditions if any new field is being added!
							if (_imgInfo.title != imgInfo.title || _imgInfo.alt != imgInfo.alt || _imgInfo.caption != imgInfo.caption
								|| _imgInfo.width != imgInfo.width || _imgInfo.height != imgInfo.height
								|| _imgInfo.align != imgInfo.align || _imgInfo.className != imgInfo.className
								|| _imgInfo.id != imgInfo.id || _imgInfo.href != imgInfo.href || _imgInfo.target != imgInfo.target) {
								isAnyChangeMade = true;
							}

							if (imgInfo.alt) {
								$img.attr('alt', imgInfo.alt);
							}
							else {
								$img.removeAttr('alt');
							}

							if (imgInfo.title) {
								$img.attr('title', imgInfo.title);
							}
							else {
								$img.removeAttr('title');
							}

							if (imgInfo.width) {
								$img.css('width', imgInfo.width);
							}

							if (imgInfo.height) {
								$img.css('height', imgInfo.height);
							}

							// CSS 类 / ID：先写入，避免覆盖随后加上的 align-* 对齐类
							$img.attr('class', imgInfo.className || null);
							$img.attr('id', imgInfo.id || null);
							$img.attr('data-align', imgInfo.align || '');

							// 对齐：优先作用于包裹的 figure，否则作用于图片本身
							var $alignTarget = $img.closest('figure').length ? $img.closest('figure') : $img;
							$alignTarget.css({ float: '', marginLeft: '', marginRight: '', display: '' });
							$alignTarget.removeClass('align-left align-center align-right');
							if (imgInfo.align === 'left' || imgInfo.align === 'right') {
								$alignTarget.css('float', imgInfo.align);
								$alignTarget.addClass('align-' + imgInfo.align);
							} else if (imgInfo.align === 'center') {
								$alignTarget.css({ display: 'block', marginLeft: 'auto', marginRight: 'auto' });
								$alignTarget.addClass('align-center');
							}

							var captionText = imgInfo.caption;
							var $parentAnchorLink = $img.parent();

							// If caption are not same, then its mean need to update!
							var isUpdateCaption = (captionText !== _imgInfo.caption);

							// If image already have a caption and is equal to old one, then remove that!
							var $imgFigure = $img.closest('figure');
							if ($imgFigure.length && isUpdateCaption) {

								// Means image wrpped in figure
								$imgFigure.find('figcaption').remove();
								$imgFigure.children().first().unwrap();

							}

							// If caption text is present then add that caption, otherwise don't do any thing
							if (isUpdateCaption && captionText) {
								var $newFigure;
								if ($parentAnchorLink.is('a')) {
									$newFigure = $parentAnchorLink.wrap(`<figure class="${options.imageAttributes.figureClass}"></figure>`).parent();
									$newFigure.append(`<figcaption class="${options.imageAttributes.figcaptionClass}"> ${captionText}</figcaption>`);
								} else {
									$newFigure = $img.wrap(`<figure class="${options.imageAttributes.figureClass}"></figure>`).parent();
									$img.after(`<figcaption class="${options.imageAttributes.figcaptionClass}">${captionText}</figcaption>`);
								}
							}

							// 点击链接：在 caption 处理完成后再包 a，避免干扰 figure/figcaption 逻辑
							var $curLink = $img.closest('a');
							if (imgInfo.href) {
								var $linkWrap = $img.closest('figure').length ? $img.closest('figure') : $img;
								if ($curLink.length) {
									$curLink.attr('href', imgInfo.href);
									if (imgInfo.target) { $curLink.attr('target', imgInfo.target); } else { $curLink.removeAttr('target'); }
								} else {
									var $newLink = $('<a></a>').attr('href', imgInfo.href);
									if (imgInfo.target) { $newLink.attr('target', imgInfo.target); }
									$linkWrap.wrap($newLink);
								}
							} else if ($curLink.length) {
								$curLink.replaceWith($curLink.children());
							}

							if (isAnyChangeMade) {
								var _content = context.invoke('code');

								$note.val(_content);
								$note.trigger('summernote.change', _content);
							}

						});
				};

				this.showLinkDialog = function (imgInfo) {
					return $.Deferred(function (deferred) {
						var $imageTitle = self.$dialog.find('.note-image-title-text');
						var $imageCaption = self.$dialog.find('.note-image-caption-text');
						var $imageAlt = self.$dialog.find('.note-image-alt-text');
						var $editBtn = self.$dialog.find('.note-image-title-btn');
						var $imageWidthInput = self.$dialog.find('.note-image-width');
						var $imageHeightInput = self.$dialog.find('.note-image-height');
						var $imageAlign = self.$dialog.find('.note-image-align');
						var $imageClass = self.$dialog.find('.note-image-class');
						var $imageId = self.$dialog.find('.note-image-id');
						var $imageHref = self.$dialog.find('.note-image-href');
						var $imageTarget = self.$dialog.find('.note-image-target');
						var $lockButton = self.$dialog.find('.lock-button');
						var $resetSizeButton = self.$dialog.find('.reset-size-buttton');
						var $unlockIcon = $lockButton.find('.icon-unlock');
						var $lockIcon = $lockButton.find('.icon-lock');

						var isLocked = (typeof options.imageAttributes.manageAspectRatio === 'undefined') ? true: options.imageAttributes.manageAspectRatio;
						if(isLocked){
							$unlockIcon.hide();
							$lockIcon.show();
						}
						else {
							$unlockIcon.show();
							$lockIcon.hide();
						}

						$lockButton.on('click', function (event) {
							event.preventDefault();
							isLocked = !isLocked;

							if (isLocked) {
								$unlockIcon.hide();
								$lockIcon.show();
								$imageHeightInput.val(imageAdjustedHeight($imageWidthInput.val(), imgInfo.naturalWidth, imgInfo.naturalHeight));
							}
							else {
								$unlockIcon.show();
								$lockIcon.hide();
							}
						});

						$resetSizeButton.on('click', function (event) {
							event.preventDefault();
							$imageWidthInput.val(imgInfo.width);
							$imageHeightInput.val(imgInfo.height);
						});

						$imageHeightInput.on("input", function () {
							if (isLocked) {
								$imageWidthInput.val(imageAdjustedWidth(this.value, imgInfo.naturalWidth, imgInfo.naturalHeight));
							}
						});

						$imageWidthInput.on("input", function () {
							if (isLocked) {
								$imageHeightInput.val(imageAdjustedHeight(this.value, imgInfo.naturalWidth, imgInfo.naturalHeight));
							}
						});

						ui.onDialogShown(self.$dialog, function () {
							context.triggerEvent('dialog.shown');

							$editBtn.on('click', function (event) {
								event.preventDefault();
								deferred.resolve({
									title: $imageTitle.val(),
									alt: $imageAlt.val(),
									caption: $imageCaption.val(),
									width: $imageWidthInput.val(),
									height: $imageHeightInput.val(),
									align: $imageAlign.val() || '',
									className: $imageClass.val() || '',
									id: $imageId.val() || '',
									href: $imageHref.val() || '',
									target: $imageTarget.val() || '',
								});
							});

							$imageTitle.val(imgInfo.title).trigger('focus');
							self.bindEnterKey($imageTitle, $editBtn);

							$imageAlt.val(imgInfo.alt);
							self.bindEnterKey($imageAlt, $editBtn);

							$imageCaption.val(imgInfo.caption);
							self.bindEnterKey($imageCaption, $editBtn);

							$imageWidthInput.val(imgInfo.width);
							self.bindEnterKey($imageWidthInput, $editBtn);

							$imageHeightInput.val(imgInfo.height);
							self.bindEnterKey($imageHeightInput, $editBtn);

							$imageAlign.val(imgInfo.align || '');
							$imageClass.val(imgInfo.className || '');
							$imageId.val(imgInfo.id || '');
							$imageHref.val(imgInfo.href || '');
							$imageTarget.val(imgInfo.target || '');
							self.bindEnterKey($imageClass, $editBtn);
							self.bindEnterKey($imageId, $editBtn);
							self.bindEnterKey($imageHref, $editBtn);

						});

						ui.onDialogHidden(self.$dialog, function () {
							$editBtn.off('click');
							$imageWidthInput.off('input');
							$imageHeightInput.off('input');
							$lockButton.off('click');
							$resetSizeButton.off('click');
							$unlockIcon.off('click');
							$lockIcon.off('click');


							if (deferred.state() === 'pending') {
								deferred.reject();
							}
						});
						ui.showDialog(self.$dialog);
					});
				};

				function imageAdjustedHeight(heightInputValue, imageOriginalWidth, imageOriginalHeight) {
					return parseInt(heightInputValue * (imageOriginalHeight / imageOriginalWidth), 10)
				}

				function imageAdjustedWidth(widthInputValue, imageOriginalWidth, imageOriginalHeight) {
					return parseInt(widthInputValue * (imageOriginalWidth / imageOriginalHeight), 10)
				}
			}
		});
	}));
  