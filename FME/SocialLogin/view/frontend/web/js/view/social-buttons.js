
define(
    [
        'jquery',
        'ko',
        'uiComponent',
        'socialProvider'
    ],
    function ($, ko, Component, socialProvider) {
        'use strict';

        ko.bindingHandlers.socialButton = {
            init: function (element, valueAccessor, allBindings) {
                var config = {
                    url: allBindings.get('url'),
                    label: allBindings.get('label')
                };

                socialProvider(config, element);
            }
        };

        return Component.extend(
            {
                defaults: {
                    template: 'FME_SocialLogin/social-buttons'
                },
                buttonLists: window.socialAuthenticationPopup,

                socials: function () {
                    var socials = [];
                    $.each(
                        this.buttonLists, function (key, social) {
                            socials.push(social);
                        }
                    );

                    return socials;
                },

                isActive: function () {
                    return (typeof this.buttonLists !== 'undefined');
                }
            }
        );
    }
);
