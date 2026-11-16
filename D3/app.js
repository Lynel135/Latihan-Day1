(function () {
  'use strict';

  var DEFAULT_IMAGE = 'image.jpg';

  var PADDING_RATIO = 0.035;
  var LOGO_WIDTH_RATIO = 0.2;
  var LOGO_MAX_HEIGHT_RATIO = 0.2;

  var canvas = document.getElementById('canvas');
  var ctx = canvas.getContext('2d');
  var caption = document.getElementById('caption');
  var statusEl = document.getElementById('status');
  var downloadBtn = document.getElementById('download');
  var imageInput = document.getElementById('image-input');
  var imageHint = document.getElementById('image-hint');
  var logoInput = document.getElementById('logo-input');
  var customName = document.getElementById('custom-name');
  var radios = Array.prototype.slice.call(document.querySelectorAll('input[name="logo"]'));

  var state = {
    image: null,
    imageName: DEFAULT_IMAGE,
    logo: null,
    logoName: 'logo1.png',
    lastPreset: 'logo1.png'
  };

  function setStatus(message, tone) {
    statusEl.textContent = message || '';
    if (tone) {
      statusEl.dataset.tone = tone;
    } else {
      delete statusEl.dataset.tone;
    }
  }

  function loadImage(src) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      img.onload = function () {
        resolve(img);
      };
      img.onerror = function () {
        reject(new Error('Could not load ' + src));
      };
      img.src = src;
    });
  }

  function placementFor(name) {
    var lower = (name || '').toLowerCase();
    if (lower.indexOf('logo2') !== -1) {
      return 'top-right';
    }
    return 'bottom-left';
  }

  function logoGeometry(imageWidth, imageHeight, logo) {
    var naturalWidth = logo.naturalWidth || logo.width;
    var naturalHeight = logo.naturalHeight || logo.height;
    var ratio = naturalHeight / naturalWidth;
    var padding = Math.round(Math.min(imageWidth, imageHeight) * PADDING_RATIO);

    var width = Math.round(imageWidth * LOGO_WIDTH_RATIO);
    var height = Math.round(width * ratio);

    var maxHeight = Math.round(imageHeight * LOGO_MAX_HEIGHT_RATIO);
    if (height > maxHeight) {
      height = maxHeight;
      width = Math.round(height / ratio);
    }

    var maxWidth = imageWidth - padding * 2;
    if (width > maxWidth) {
      width = maxWidth;
      height = Math.round(width * ratio);
    }

    return { width: width, height: height, padding: padding };
  }

  function render() {
    if (!state.image) {
      return;
    }

    var imageWidth = state.image.naturalWidth || state.image.width;
    var imageHeight = state.image.naturalHeight || state.image.height;

    canvas.width = imageWidth;
    canvas.height = imageHeight;
    ctx.clearRect(0, 0, imageWidth, imageHeight);
    ctx.drawImage(state.image, 0, 0, imageWidth, imageHeight);

    var placement = placementFor(state.logoName);

    if (state.logo) {
      var size = logoGeometry(imageWidth, imageHeight, state.logo);
      var x = placement === 'top-right'
        ? imageWidth - size.width - size.padding
        : size.padding;
      var y = placement === 'top-right'
        ? size.padding
        : imageHeight - size.height - size.padding;
      ctx.drawImage(state.logo, x, y, size.width, size.height);
    }

    var label = placement === 'top-right' ? 'top right' : 'bottom left';
    caption.textContent = state.logo
      ? state.logoName + ' · ' + label + ' · ' + imageWidth + ' × ' + imageHeight + ' px'
      : state.imageName + ' · ' + imageWidth + ' × ' + imageHeight + ' px';
  }

  function usePresetLogo(name) {
    setStatus('Loading ' + name + '…');
    return loadImage(name)
      .then(function (img) {
        state.logo = img;
        state.logoName = name;
        state.lastPreset = name;
        customName.textContent = 'choose file…';
        render();
        setStatus('Ready. Placement: ' + placementFor(name).replace('-', ' ') + '.');
      })
      .catch(function (error) {
        state.logo = null;
        render();
        setStatus(error.message, 'error');
      });
  }

  function useLogoFile(file) {
    var url = URL.createObjectURL(file);
    setStatus('Loading ' + file.name + '…');
    return loadImage(url)
      .then(function (img) {
        state.logo = img;
        state.logoName = file.name;
        customName.textContent = file.name;
        render();
        setStatus('Ready. Placement: ' + placementFor(file.name).replace('-', ' ') + '.');
      })
      .catch(function (error) {
        state.logo = null;
        render();
        setStatus(error.message, 'error');
      })
      .then(function () {
        URL.revokeObjectURL(url);
      });
  }

  function useImageFile(file) {
    var url = URL.createObjectURL(file);
    setStatus('Loading ' + file.name + '…');
    return loadImage(url)
      .then(function (img) {
        state.image = img;
        state.imageName = file.name;
        imageHint.textContent = 'Using ' + file.name;
        render();
        setStatus('Ready.');
      })
      .catch(function (error) {
        setStatus(error.message, 'error');
      })
      .then(function () {
        URL.revokeObjectURL(url);
      });
  }

  function selectRadio(value) {
    radios.forEach(function (radio) {
      radio.checked = radio.value === value;
    });
  }

  radios.forEach(function (radio) {
    radio.addEventListener('change', function () {
      if (!radio.checked) {
        return;
      }
      if (radio.value === 'custom') {
        logoInput.click();
        return;
      }
      usePresetLogo(radio.value);
    });
  });

  logoInput.addEventListener('change', function () {
    var file = logoInput.files && logoInput.files[0];
    if (!file) {
      selectRadio(state.lastPreset);
      return;
    }
    selectRadio('custom');
    useLogoFile(file);
    logoInput.value = '';
  });

  imageInput.addEventListener('change', function () {
    var file = imageInput.files && imageInput.files[0];
    if (!file) {
      return;
    }
    useImageFile(file);
    imageInput.value = '';
  });

  downloadBtn.addEventListener('click', function () {
    if (!state.image) {
      return;
    }

    var baseName = state.imageName.replace(/\.[^.]+$/, '') || 'image';
    var logoBase = state.logoName.replace(/\.[^.]+$/, '') || 'watermark';
    var fileName = 'watermarked-' + baseName + '-' + logoBase + '.png';

    try {
      canvas.toBlob(function (blob) {
        if (!blob) {
          setStatus('Export failed. Serve this folder over HTTP (for example `php -S localhost:8000`) and try again.', 'error');
          return;
        }
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        setStatus('Saved ' + fileName + '.');
      }, 'image/png');
    } catch (error) {
      setStatus('Export failed: ' + error.message + '. Serve the folder over HTTP to allow saving.', 'error');
    }
  });

  downloadBtn.disabled = true;

  loadImage(DEFAULT_IMAGE)
    .then(function (img) {
      state.image = img;
      state.imageName = DEFAULT_IMAGE;
      downloadBtn.disabled = false;
      return usePresetLogo('logo1.png');
    })
    .catch(function (error) {
      caption.textContent = 'No image loaded';
      setStatus(error.message + '. Choose an image file to begin.', 'error');
    });
})();
