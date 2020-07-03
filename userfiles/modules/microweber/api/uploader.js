var Uploader = function( options ) {
    //var upload = function( url, data, callback, type ) {
    options = options || {};
    var defaults = {
        multiple: false,
        progress: null,
        element: null,
        url: options.url || (mw.settings.site_url + 'plupload'),
        urlParams: {},
        on: {},
        autostart: true,
        async: true,
        accept: '*',
        chunkSize: 1500000,
    };

    var scope = this;
    this.settings = $.extend({}, defaults, options);

    this.getUrl = function () {
        var params = this.urlParams();
        var empty = mw.tools.isEmptyObject(params);
        return this.url() + (empty ? '' : ('?' + $.param(params)));
    };

    this.urlParam = function (param, value) {
        if(typeof value === 'undefined') {
            return this.settings.urlParams[param];
        }
        this.settings.urlParams[param] = value;
    };

    this.urlParams = function (params) {
        if(!params) {
            return this.settings.urlParams;
        }
        this.settings.urlParams = params;
    };

    this.url = function (url) {
        if(!url) {
            return this.settings.url;
        }
        this.settings.url = url;
    };

    this.create = function () {
        this.input = document.createElement('input');
        this.input.multiple = this.settings.multiple;
        this.input.accept = this.settings.accept;
        this.input.type = 'file';
        this.input.className = 'mw-uploader-input';
        this.input.oninput = function () {
            scope.addFiles(this.files);
        };
    };

    this.files = [];
    this._uploading = false;
    this.uploading = function (state) {
        if(typeof state === 'undefined') {
            return this._uploading;
        }
        this._uploading = state;
    };

    this.validate = function (file) {
        return true;
    };

    this.addFile = function (file) {
        if(this.validate(file)) {
            if(!this.files.length || this.settings.multiple){
                this.files.push(file);
                if(this.settings.on.fileAdded) {
                    this.settings.on.fileAdded(file);
                }
                $(scope).trigger('FileAdded', file);
            } else {
                this.files = [file];
                $(scope).trigger('FileAdded', file);
                if(this.settings.on.fileAdded) {
                    this.settings.on.fileAdded(file);
                }
            }
        }
    };

    this.addFiles = function (files) {
        if (files && files.length) {
            for (var i = 0; i < files.length; i++) {
                scope.addFile(files[i]);
            }
            if(this.settings.on.filesAdded) {
                if(this.settings.on.filesAdded(files) === false) {
                    return;
                }
            }
            $(scope).trigger('FilesAdded', files);
            if(this.settings.autostart) {
                this.uploadFiles();
            }
        }
    };

    this.build = function () {
        if(this.settings.element) {
            this.$element = $(this.settings.element);
            this.element = this.$element[0];
            if(this.element) {
                this.$element/*.empty()*/.append(this.input);
            }
        }
    };

    this.show = function () {
        this.$element.show();
    };

    this.hide = function () {
        this.$element.hide();
    };

    this.init = function() {
        this.create();
        this.build();
    };

    this.init();

    this.removeFile = function (file) {
        var i = this.files.indexOf(file);
        if (i > -1) {
            this.files.splice(i, 1);
        }
    };

    this.uploadFile = function (file, done, _chunks, _all, _i) {
        var chunks = _chunks || this.sliceFile(file);
        _all = _all || chunks.length;
        _i = _i || 0;
        var chunk = chunks.shift();
        var data = {
            name: file.name,
            chunk: _i,
            chunks: _all,
            file: chunk,
        };
        _i++;
        this.upload(data, function () {
            if(chunks.length) {
                scope.uploadFile(file, done, _chunks, _all, _i);
            } else {
                done.call(file);
            }
        });

    };

    this.sliceFile = function(file) {
        var byteIndex = 0;
        var chunks = [];
        var chunksAmount = file.size <= this.settings.chunkSize ? 1 : ((file.size / this.settings.chunkSize) >> 0) + 1;

        for (var i = 0; i < chunksAmount; i ++) {
            var byteEnd = Math.ceil((file.size / chunksAmount) * (i + 1));
            chunks.push(file.slice(byteIndex, byteEnd));
            byteIndex += (byteEnd - byteIndex);
        }

        return chunks;
    };

    this.uploadFiles = function () {
        if (this.settings.async) {
            if (this.files.length) {
                this.uploading(true);
                this.uploadFile(this.files[0], function () {
                    scope.files.shift();
                    scope.uploadFiles();
                });
            } else {
                this.uploading(false);
                scope.input.value = '';
                if(scope.settings.on.filesUploaded) {
                    scope.settings.on.filesUploaded();
                }
                $(scope).trigger('FilesUploaded');
            }
        } else {
            var count = 0;
            var all = this.files.length;
            this.uploading(true);
            this.files.forEach(function (file) {
                scope.uploadFile(file, function () {
                    count++;
                    scope.uploading(false);
                    if(all === count) {
                        scope.input.value = '';
                        if(scope.settings.on.filesUploaded) {
                            scope.settings.on.filesUploaded();
                        }
                        $(scope).trigger('FilesUploaded');
                    }
                });
            });
        }
    };

    this.upload = function (data, done) {
        if (!this.settings.url) {
            return;
        }
        var pdata = new FormData();
        $.each(data, function (key, val) {
            pdata.append(key, val);
        });
        if(scope.settings.on.uploadStart) {
            if (scope.settings.on.uploadStart(pdata) === false) {
                return;
            }
        }
        $(scope).trigger('uploadStart', [pdata]);
        return $.ajax({
            url: this.getUrl(),
            type: 'post',
            processData: false,
            contentType: false,
            data: pdata,
            success: function (res) {
                scope.removeFile(data.file);
                if(done) {
                    done.call(res);
                }
                if(scope.settings.on.fileUploaded) {
                    scope.settings.on.fileUploaded(res);
                }
                $(scope).trigger('FileUploaded', res);
            },
            dataType: 'json',
            xhr: function () {
                var xhr = new XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (event) {
                    if (event.lengthComputable) {
                        var percent = (event.loaded / event.total) * 100;
                        if(scope.settings.on.progress) {
                            scope.settings.on.progress(percent, event);
                        }
                        $(scope).trigger('progress', [percent, event]);
                    }
                });
                return xhr;
            }
        });
    };
};

mw.upload = function (options) {
    return new Uploader(options);
};
