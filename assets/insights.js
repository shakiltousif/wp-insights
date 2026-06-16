/* Shakvaro WP Insights — deactivation survey on the Plugins screen. */
(function ($) {
    'use strict';

    // Collect every per-plugin config the SDK localized (key prefix ShakvaroInsights_).
    var configs = [];
    for (var k in window) {
        if (k.indexOf('ShakvaroInsights_') === 0 && window[k] && window[k].slug) {
            configs.push(window[k]);
        }
    }
    if (!configs.length) {
        return;
    }

    $(function () {
        configs.forEach(function (cfg) {
            if (!cfg.basename) {
                return;
            }

            var $row = $('tr[data-plugin="' + cfg.basename + '"]');
            var $link = $row.find('.deactivate a');
            if (!$link.length) {
                return;
            }

            var $modal = $('#' + cfg.modalId);
            var targetUrl = null;

            $link.on('click', function (e) {
                if (!$modal.length) {
                    return; // no modal → normal deactivate
                }
                e.preventDefault();
                targetUrl = $(this).attr('href');
                $modal.show();
            });

            function proceed() {
                $modal.hide();
                if (targetUrl) {
                    window.location.href = targetUrl;
                }
            }

            $modal.on('click', '.shakvaro-modal__skip', function (e) {
                e.preventDefault();
                proceed();
            });

            $modal.on('click', '.shakvaro-modal__submit', function (e) {
                e.preventDefault();
                var reason = $modal.find('input[name="shakvaro_reason"]:checked').val() || '';
                var text = $modal.find('.shakvaro-modal__text').val() || '';

                $.post(cfg.ajaxUrl, {
                    action: cfg.action,
                    nonce: cfg.nonce,
                    reason_code: reason,
                    reason_text: text
                }).always(proceed);
            });
        });
    });
})(jQuery);
