var video=document.getElementById ('video');
if (video)
video.addEventListener('timeupdate', (event) => {
    $('#video_position').val(video.currentTime);
  });