const blobSlice = File.prototype.slice || File.prototype.mozSlice || File.prototype.webkitSlice;
const chunkSize = 1048576;

let mediaFiles = {};
let nextFileId = 0;
let trainMusic = null;
let playing = false;

let updateTimeout = null;

$(document).ready(function() {

    $('body').scrollspy({ target: '#header', offset: 400});
    
    /* фиксация шапки */
    
    $(window).bind('scroll', function() {
         if ($(window).scrollTop() > 50) {
             $('#header').addClass('navbar-fixed-top');
         }
         else {
             $('#header').removeClass('navbar-fixed-top');
         }
    });
   
    /* ======= ScrollTo ======= */
    $('a.scrollto').on('click', function(e){
        
        var target = this.hash;
                
        e.preventDefault();
        
		$('body').scrollTo(target, 800, {offset: -70, 'axis':'y', easing:'easeOutQuad'});
        
		if ($('.navbar-collapse').hasClass('in')){
			$('.navbar-collapse').removeClass('in').addClass('collapse');
		}
		
	});

    trainMusic = new Audio('static/audio/poezd.mp3'); 
    trainMusic.loop = true;
    trainMusic.play();
    playing = true;

    initUi();
    updateLoop();
});

function initUi() {
    $('#upload-media-file').change(function() {
        const files = $(this).get(0).files;
        $.each(files, function(index, file) {
            nextFileId++;
            file.id = nextFileId;
            file.status = 0;
            mediaFiles['file' + nextFileId] = file;

            addQueueItem(file);
            addAudioFileToQueue(file).then(
                data => {
                    console.log(data);
                },
                error => {
                    console.error(error);
                }
            );
        });
        let target = $('#queue');
        $('body').scrollTo(target, 800, {offset: -70, 'axis':'y', easing:'easeOutQuad'});
    });
    $('.train').click(() => {
        if (playing) {
            trainMusic.volume = 0;
            playing = false;
        } else {
            trainMusic.volume = 1;
            playing = true;
        }
    });
}

function updateFileStatusByFileId(fileId) {
    const file = mediaFiles['file' + fileId];
    const queueItemStatus = $('.queue-item[file-id="' + fileId + '"] .file-status');
    if (queueItemStatus) {
        queueItemStatus.html(getStatusName(file.status));
        console.log(queueItemStatus);
    } else {
        console.error('file item not found');
    }
}

function fancyTimeFormat(duration) {
    // Hours, minutes and seconds
    const hrs = ~~(duration / 3600);
    const mins = ~~((duration % 3600) / 60);
    const secs = ~~duration % 60;
  
    // Output like "1:01" or "4:03:59" or "123:03:59"
    let ret = "";
  
    if (hrs > 0) {
      ret += "" + hrs + ":" + (mins < 10 ? "0" : "");
    }
  
    ret += "" + mins + ":" + (secs < 10 ? "0" : "");
    ret += "" + secs;
  
    return ret;
  }

function addQueueItem(file) {
    const queueItem = $('<div class="queue-item">');
    queueItem.attr('file-id', file.id);

    const logoElement = $(
        '<div class="row audio-logo">' +
            '<svg height="32px" width="32px" version="1.1" id="_x32_" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512"  xml:space="preserve">' +
                '<style type="text/css">' +
                    '.st0 {fill: var(--color-primary); }' +
                '</style>' +
                '<g>' +
                    '<path class="st0" d="M378.409,0H208.294h-13.175l-9.315,9.314L57.016,138.102l-9.314,9.314v13.176v265.513c0,47.361,38.528,85.896,85.896,85.896h244.811c47.36,0,85.888-38.535,85.888-85.896V85.895C464.298,38.528,425.769,0,378.409,0zM432.494,426.104c0,29.877-24.215,54.092-54.084,54.092H133.598c-29.877,0-54.092-24.215-54.092-54.092V160.591h83.717c24.885,0,45.07-20.179,45.07-45.07V31.804h170.116c29.87,0,54.084,24.214,54.084,54.091V426.104z"/>' +
                    '<path class="st0" d="M204.576,254.592v76.819c-6.483-2.388-14.156-3.093-22.054-1.571c-18.974,3.651-32.038,18.622-29.185,33.424c2.856,14.809,20.538,23.851,39.516,20.192c16.837-3.24,29-15.403,29.521-28.474h0.104V253.413l90.934-13.671v75.632c-6.486-2.381-14.157-3.079-22.054-1.557c-18.974,3.644-32.035,18.615-29.186,33.424c2.856,14.802,20.538,23.844,39.516,20.186c16.838-3.247,29.001-15.403,29.514-28.466h0.112V238.562v-33.368l-126.738,16.024V254.592z"/>' +
                '</g>' +
            '</svg>' +
            '<h2>Аудио-файл #' + file.id + '</h2>' +
        '</div>'
    );
    logoElement.appendTo(queueItem);

    const audioElement = $('<audio controls>');
    audioElement.appendTo(queueItem);

    const nameRow = $('<div class="row">');
    const nameLabel = $('<div class="label2">').html('Название файла:');
    const nameDiv = $('<div class="file-name">').html(file.name);
    nameLabel.appendTo(nameRow);
    nameDiv.appendTo(nameRow);

    const durationRow = $('<div class="row">');
    const durationLabel = $('<div class="label2">').html('Длительность:');
    const durationDiv = $('<div class="file-duration">');
    durationLabel.appendTo(durationRow);
    durationDiv.appendTo(durationRow);

    const statusRow = $('<div class="row">');
    const statusLabel = $('<div class="label2">').html('Статус:');
    const statusDiv = $('<div class="file-status">');
    statusLabel.appendTo(statusRow);
    statusDiv.appendTo(statusRow);

    nameRow.appendTo(queueItem);
    durationRow.appendTo(queueItem);
    statusRow.appendTo(queueItem);

    $('#queue-container').append(queueItem);
    updateFileStatusByFileId(file.id);

    let audio = $(audioElement)[0];
    let reader = new FileReader();
    reader.onloadend = function (e) {
        const ctx = new AudioContext();
        const audioArrayBuffer = e.target.result;
        ctx.decodeAudioData(audioArrayBuffer, data => {
            const duration = data.duration;
            durationDiv.html(fancyTimeFormat(duration));
        }, error => {
            console.error(error);
        });
        let reader2 = new FileReader();
        reader2.onloadend = function (e2) {
            audio.src = e2.target.result;
        };
        reader2.readAsDataURL(file);
    };
    reader.readAsArrayBuffer(file);
}

function addAnalyzeItem(sha1Hash) {
    const it = $('.analyzed-item[sha1-hash="' + sha1Hash + '"]').length;
    if (it) {
        return;
    } 

    const analyzedItem = $('<div class="analyzed-item">');
    analyzedItem.attr('sha1-hash', sha1Hash);

    const logoElement = $(
        '<div class="row audio-logo">' +
            '<svg height="32px" width="32px" version="1.1" id="_x32_" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512"  xml:space="preserve">' +
                '<style type="text/css">' +
                    '.st0 {fill: var(--color-primary); }' +
                '</style>' +
                '<g>' +
                    '<path class="st0" d="M378.409,0H208.294h-13.175l-9.315,9.314L57.016,138.102l-9.314,9.314v13.176v265.513c0,47.361,38.528,85.896,85.896,85.896h244.811c47.36,0,85.888-38.535,85.888-85.896V85.895C464.298,38.528,425.769,0,378.409,0zM432.494,426.104c0,29.877-24.215,54.092-54.084,54.092H133.598c-29.877,0-54.092-24.215-54.092-54.092V160.591h83.717c24.885,0,45.07-20.179,45.07-45.07V31.804h170.116c29.87,0,54.084,24.214,54.084,54.091V426.104z"/>' +
                    '<path class="st0" d="M204.576,254.592v76.819c-6.483-2.388-14.156-3.093-22.054-1.571c-18.974,3.651-32.038,18.622-29.185,33.424c2.856,14.809,20.538,23.851,39.516,20.192c16.837-3.24,29-15.403,29.521-28.474h0.104V253.413l90.934-13.671v75.632c-6.486-2.381-14.157-3.079-22.054-1.557c-18.974,3.644-32.035,18.615-29.186,33.424c2.856,14.802,20.538,23.844,39.516,20.186c16.838-3.247,29.001-15.403,29.514-28.466h0.112V238.562v-33.368l-126.738,16.024V254.592z"/>' +
                '</g>' +
            '</svg>' +
            '<h2>Анализ #' + sha1Hash + '</h2>' +
        '</div>'
    );
    logoElement.appendTo(analyzedItem);

/*
    const audioElement = $('<audio controls>');
    audioElement.appendTo(queueItem);

    const nameRow = $('<div class="row">');
    const nameLabel = $('<div class="label2">').html('Название файла:');
    const nameDiv = $('<div class="file-name">').html(file.name);
    nameLabel.appendTo(nameRow);
    nameDiv.appendTo(nameRow);

    const durationRow = $('<div class="row">');
    const durationLabel = $('<div class="label2">').html('Длительность:');
    const durationDiv = $('<div class="file-duration">');
    durationLabel.appendTo(durationRow);
    durationDiv.appendTo(durationRow);

    const statusRow = $('<div class="row">');
    const statusLabel = $('<div class="label2">').html('Статус:');
    const statusDiv = $('<div class="file-status">');
    statusLabel.appendTo(statusRow);
    statusDiv.appendTo(statusRow);

    nameRow.appendTo(queueItem);
    durationRow.appendTo(queueItem);
    statusRow.appendTo(queueItem);
*/
const statusRow = $('<div class="row">');
    const statusLabel = $('<div class="label2">').html('Результат анализа:');
    const statusDiv = $('<div class="analyze-status">').html('Найдены ошибки');
    statusLabel.appendTo(statusRow);
    statusDiv.appendTo(statusRow);
    statusRow.appendTo(analyzedItem);
    $('#analyzed-container').append(analyzedItem);
    //updateFileStatusByFileId(file.id);

    // let audio = $(audioElement)[0];
    // let reader = new FileReader();
    // reader.onloadend = function (e) {
    //     const ctx = new AudioContext();
    //     const audioArrayBuffer = e.target.result;
    //     ctx.decodeAudioData(audioArrayBuffer, data => {
    //         const duration = data.duration;
    //         durationDiv.html(fancyTimeFormat(duration));
    //     }, error => {
    //         console.error(error);
    //     });
    //     let reader2 = new FileReader();
    //     reader2.onloadend = function (e2) {
    //         audio.src = e2.target.result;
    //     };
    //     reader2.readAsDataURL(file);
    // };
    // reader.readAsArrayBuffer(file);
}

function arrayBufferToWordArray(ab) {
    var i8a = new Uint8Array(ab);
    var a = [];
    for (var i = 0; i < i8a.length; i += 4) {
      a.push(i8a[i] << 24 | i8a[i + 1] << 16 | i8a[i + 2] << 8 | i8a[i + 3]);
    }
    return CryptoJS.lib.WordArray.create(a, i8a.length);
}

function addAudioFileToQueueRequest(file) {
    return new Promise((resolve, reject) => {
        const formData = new FormData();
        formData.append('sha1_hash', file.sha1_hash);
        formData.append('file_size', file.size);
        formData.append('user_file_upload', file);
        $.ajax({
            url: '?act=add_audio_file_to_queue',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(data, textStatus, jqXHR) {
                if (data.answer && data.answer == 'ok') {
                    file.status = 2;
                    updateFileStatusByFileId(file.id);
                    resolve(data);
                } else {
                    reject('unknown_error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) { 
                reject('post_error');
            }
        });
    });
}

function addAudioFileToQueue(file) {
    return new Promise((resolve, reject) => {
        if (!file) {
            reject('Wrong file!');
            return;
        }

        const totalChunks = Math.ceil(file.size / chunkSize);
        const fileReader = new FileReader();
        let currentChunk = 0;
        let sha1 = CryptoJS.algo.SHA1.create();
        let tmp;

        fileReader.onload = function (e) {
            tmp = arrayBufferToWordArray(e.target.result);

            sha1.update(tmp);

            currentChunk++;

            if (currentChunk < totalChunks) {
                loadNext();
            } else {
                const sha1Hash = sha1.finalize().toString(CryptoJS.enc.Hex);
                file.sha1_hash = sha1Hash;

                file.status = 1;
                updateFileStatusByFileId(file.id);
                addAudioFileToQueueRequest(file)
                .then(
                    result => {
                        resolve(result);
                    },
                    error => {
                        reject(error);
                    }
                );
            }
        };

        fileReader.onerror = function () {
            reject(fileReader.error);
        };

        function loadNext() {
            const start = currentChunk * chunkSize;
            const end = ((start + chunkSize) >= file.size) ? file.size : start + chunkSize;

            fileReader.readAsArrayBuffer(blobSlice.call(file, start, end));
        }

        loadNext();
    });
}

function updateLoop() {
    updateTimeout = setTimeout(function() {
        $.post('?act=get_data', null)
        .done(function (data) {
            if (data.answer && data.answer == 'ok') {
                console.log(data);
                $.each(data.files, function(index, file) {
                    addAnalyzeItem(file.sha1_hash);
                });
            } else {
                console.error('error update');
            }
            updateLoop();
        }).fail(function () {
            console.error('error update');
            updateLoop();
        });
    }, 3000);
}

function getStatusName(status) {
    let statusName = ''
    switch (status) {
        case 0:
            statusName = 'Предзагрузка';
            break;
        case 1:
            statusName = 'Загрузка файла на сервер';
            break;
        case 2:
            statusName = 'Добавлено в очередь';
            break;
        case 3:
            statusName = 'Предобработка файла';
            break;
        case 4:
            statusName = 'Транскрибирование аудио';
            break;
        case 5:
            statusName = 'Анализ переговора';
            break;
        case 6:
            statusName = 'Анализ завершен';
            break;
    }
    return statusName;
}