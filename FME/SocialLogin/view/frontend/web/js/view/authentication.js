define(
    [
        'jquery',
        'ko',
        'uiComponent',
        'Magento_Customer/js/model/customer',
        'mage/translate',
        'Magento_Ui/js/modal/modal',
        'rjsResolver'
    ],
    function ($, ko, Component, customer, $t, modal, resolver) {
        'use strict';
        return Component.extend(
            {
                defaults: {
                    template: 'FME_SocialLoginPro/authentication'
                },

                initialize: function () {
                    var self = this;
                    this._super();
                    this.popup   = $('#social-login-popup');
                    this.wrapper = $('.quick-login-wrapper');

                    resolver(
                        function () {

                            if (self.popup.length !== 0 || self.wrapper.length !== 0) {
                                $('.authentication-wrapper button').replaceWith(
                                    '    <a class="action action-auth-toggle">\n' +
                                    '        <span data-bind="i18n: \'Sign In\'">Sign In</span>\n' +
                                    '    </a>'
                                );

                                var el = $('.authentication-wrapper a');
                                el.addClass('social-login-btn');
                                el.css('cursor', 'pointer');
                            }

                            if (self.popup.length !== 0) {
                                el.attr('href', '#social-login-popup');
                                el.on('click', function () {
                                    self.popup.socialpopup('showLogin');
                                    self.popup.socialpopup('loadApi');
                                });

                                $('.authentication-wrapper').magnificPopup(
                                    {
                                        delegate: 'a.social-login-btn',
                                        removalDelay: 500,
                                        midClick: true
                                    }
                                );
                            }

                            if (self.wrapper.length !== 0) {
                                el.on(
                                    'click', function (event) {
                                        self.wrapper.socialpopup('showLogin');
                                        self.wrapper.socialpopup('openModal');
                                        event.stopPropagation();
                                    }
                                );
                            }
                        }
                    );

                    return this;
                },
                isActive: function () {
                    return !customer.isLoggedIn();
                }
            }
        );
    }
);
