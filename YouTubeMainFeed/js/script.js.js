document.addEventListener('DOMContentLoaded', function() {
    // Lightbox functionalities
    jQuery(document).ready(function ($) {
        $('.youtube-video iframe').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const videoSrc = $(this).attr('src');
            const lightbox = $('<div class="lightbox">' + 
                '<div class="lightbox-content">' +
                    '<iframe src="' + videoSrc + '?autoplay=1" frameborder="0" allowfullscreen></iframe>' +
                    '<button class="lightbox-close">✖</button>' +
                '</div>' +
            '</div>');
            $('body').append(lightbox);
            lightbox.addClass('active');
            $('body').css('overflow', 'hidden');
        });

        $(document).on('click', '.lightbox, .lightbox-close', function (e) {
            if ($(e.target).closest('iframe').length === 0) {
                $('.lightbox').remove();
                $('body').css('overflow', '');
            }
        });

        $(document).on('keyup', function (e) {
            if (e.key === 'Escape') {
                $('.lightbox').remove();
                $('body').css('overflow', '');
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    var iframes = document.querySelectorAll('.youtube-video iframe');

    iframes.forEach(function(iframe) {
        var parentDiv = iframe.parentElement;

        // Hover autoplay for desktop
        parentDiv.addEventListener('mouseenter', function() {
            setTimeout(function() {
                iframe.src = iframe.src.replace('?autoplay=0', '?autoplay=1').replace('&autoplay=0', '&autoplay=1');
                iframe.setAttribute('inert', '');
            }, 1000);
        });

        parentDiv.addEventListener('mouseleave', function() {
            iframe.src = iframe.src.replace('?autoplay=1', '?autoplay=0').replace('&autoplay=1', '&autoplay=0');
            iframe.removeAttribute('inert');
        });

        // Intersection Observer for Mobile Autoplay
        var options = {
            root: null,
            threshold: 1.0
        };
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    iframe.src = iframe.src.replace('?autoplay=0', '?autoplay=1').replace('&autoplay=0', '&autoplay=1');
                    iframe.setAttribute('inert', '');
                } else {
                    iframe.src = iframe.src.replace('?autoplay=1', '?autoplay=0').replace('&autoplay=1', '&autoplay=0');
                    iframe.removeAttribute('inert');
                }
            });
        }, observerOptions);
        observer.observe(parentDiv);
    });
});