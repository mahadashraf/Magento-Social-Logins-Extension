
define(
    [
    'jquery',
    'Magento_Customer/js/customer-data'
    ], function ($, customerData) {
        'use strict';

        window.socialCallback = function (url, windowObj) {
            customerData.invalidate(['customer']);
            customerData.reload(['customer'], true);

            if (url !== '') {
                window.location.href = url;
            } else {
                window.location.reload(true);
            }

            windowObj.close();
        };

        return function (config, element) {
            var model = {
                initialize: function () {
                    var self = this;
                    customerData.reload(true);
                    $(element).on(
                        'click', function () {
                            self.openPopup();
                        }
                    );
                },

                openPopup: function () {
                    var date = new Date(),
                        currentTime = date.getTime();
                    window.open(config.url + '?' + currentTime, config.label, this.getPopupParams());
                },

                getPopupParams: function (w, h, l, t) {
                    this.screenX = typeof window.screenX !== 'undefined' ? window.screenX : window.screenLeft;
                    this.screenY = typeof window.screenY !== 'undefined' ? window.screenY : window.screenTop;
                    this.outerWidth = typeof window.outerWidth !== 'undefined' ? window.outerWidth : document.body.clientWidth;
                    this.outerHeight = typeof window.outerHeight !== 'undefined' ? window.outerHeight : (document.body.clientHeight - 22);
                    this.width = w ? w : 500;
                    this.height = h ? h : 420;
                    this.left = l ? l : parseInt(this.screenX + ((this.outerWidth - this.width) / 2), 10);
                    this.top = t ? t : parseInt(this.screenY + ((this.outerHeight - this.height) / 2.5), 10);

                    return (
                    'width=' + this.width +
                    ',height=' + this.height +
                    ',left=' + this.left +
                    ',top=' + this.top
                    );
                }
            };
            model.initialize();

            return model;
        };
    }
);
