/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
define(["require", "exports", "jquery"], function (require, exports, $) {
    "use strict";
    var Selectors;
    (function (Selectors) {
        Selectors["toggleButton"] = ".t3js-form-field-slug-toggle";
        Selectors["recreateButton"] = ".t3js-form-field-slug-recreate";
        Selectors["inputField"] = ".t3js-form-field-slug-input";
        Selectors["readOnlyField"] = ".t3js-form-field-slug-readonly";
        Selectors["hiddenField"] = ".t3js-form-field-slug-hidden";
    })(Selectors || (Selectors = {}));
    var ProposalModes;
    (function (ProposalModes) {
        ProposalModes["AUTO"] = "auto";
        ProposalModes["RECREATE"] = "recreate";
        ProposalModes["MANUAL"] = "manual";
    })(ProposalModes || (ProposalModes = {}));
    /**
     * Module: TYPO3/CMS/Backend/FormEngine/Element/SlugElement
     * Logic for a TCA type "slug"
     *
     * For new records, changes on the other fields of the record (typically the record title) are listened
     * on as well and the response is put in as "placeholder" into the input field.
     *
     * For new and existing records, the toggle switch will allow editors to modify the slug
     *  - for new records, we only need to see if that is already in use or not (uniqueInSite), if it is taken, show a message.
     *  - for existing records, we also check for conflicts, and check if we have subpges, or if we want to add a redirect (todo)
     */
    var SlugElement = (function () {
        function SlugElement(selector, options) {
            var _this = this;
            this.options = null;
            this.$fullElement = null;
            this.manuallyChanged = false;
            this.$readOnlyField = null;
            this.$inputField = null;
            this.$hiddenField = null;
            this.xhr = null;
            this.fieldsToListenOn = {};
            this.options = options;
            this.fieldsToListenOn = this.options.listenerFieldNames || {};
            $(function () {
                _this.$fullElement = $(selector);
                _this.$inputField = _this.$fullElement.find(Selectors.inputField);
                _this.$readOnlyField = _this.$fullElement.find(Selectors.readOnlyField);
                _this.$hiddenField = _this.$fullElement.find(Selectors.hiddenField);
                _this.registerEvents();
            });
        }
        SlugElement.prototype.registerEvents = function () {
            var _this = this;
            var fieldsToListenOnList = Object.keys(this.getAvailableFieldsForProposalGeneration()).map(function (k) { return _this.fieldsToListenOn[k]; });
            // Listen on 'listenerFieldNames' for new pages. This is typically the 'title' field
            // of a page to create slugs from the title when title is set / changed.
            if (fieldsToListenOnList.length > 0) {
                if (this.options.command === 'new') {
                    $(this.$fullElement).on('keyup', fieldsToListenOnList.join(','), function () {
                        if (!_this.manuallyChanged) {
                            _this.sendSlugProposal(ProposalModes.AUTO);
                        }
                    });
                }
                // Clicking the recreate button makes new slug proposal created from 'title' field
                $(this.$fullElement).on('click', Selectors.recreateButton, function (e) {
                    e.preventDefault();
                    if (_this.$readOnlyField.hasClass('hidden')) {
                        // Switch to readonly version - similar to 'new' page where field is
                        // written on the fly with title change
                        _this.$readOnlyField.toggleClass('hidden', false);
                        _this.$inputField.toggleClass('hidden', true);
                    }
                    _this.sendSlugProposal(ProposalModes.RECREATE);
                });
            }
            else {
                $(this.$fullElement).find(Selectors.recreateButton).addClass('disabled').prop('disabled', true);
            }
            // Scenario for new pages: Usually, slug is created from the page title. However, if user toggles the
            // input field and feeds an own slug, and then changes title again, the slug should stay. manuallyChanged
            // is used to track this.
            $(this.$inputField).on('keyup', function () {
                _this.manuallyChanged = true;
                _this.sendSlugProposal(ProposalModes.MANUAL);
            });
            // Clicking the toggle button toggles the read only field and the input field.
            // Also set the value of either the read only or the input field to the hidden field
            // and update the value of the read only field after manual change of the input field.
            $(this.$fullElement).on('click', Selectors.toggleButton, function (e) {
                e.preventDefault();
                var showReadOnlyField = _this.$readOnlyField.hasClass('hidden');
                _this.$readOnlyField.toggleClass('hidden', !showReadOnlyField);
                _this.$inputField.toggleClass('hidden', showReadOnlyField);
                if (!showReadOnlyField) {
                    _this.$hiddenField.val(_this.$inputField.val());
                    return;
                }
                if (_this.$inputField.val() !== _this.$readOnlyField.val()) {
                    _this.$readOnlyField.val(_this.$inputField.val());
                }
                else {
                    _this.manuallyChanged = false;
                    _this.$fullElement.find('.t3js-form-proposal-accepted').addClass('hidden');
                    _this.$fullElement.find('.t3js-form-proposal-different').addClass('hidden');
                }
                _this.$hiddenField.val(_this.$readOnlyField.val());
            });
        };
        /**
         * @param {ProposalModes} mode
         */
        SlugElement.prototype.sendSlugProposal = function (mode) {
            // Return early when this is a new page (no suggestions, as this would lead to a wrong slug)
            if (this.options.recordId.toString().substr(0,3) === 'NEW') {
                return;
            }
            var _this = this;
            var input = {};
            if (mode === ProposalModes.AUTO || mode === ProposalModes.RECREATE) {
                $.each(this.getAvailableFieldsForProposalGeneration(), function (fieldName, field) {
                    input[fieldName] = $('[data-formengine-input-name="' + field + '"]').val();
                });
                if (this.options.includeUidInValues === true) {
                    input.uid = this.options.recordId.toString();
                }
            }
            else {
                input.manual = this.$inputField.val();
            }
            if (this.xhr !== null && this.xhr.readyState !== 4) {
                this.xhr.abort();
            }
            this.xhr = $.post(TYPO3.settings.ajaxUrls.record_slug_suggest, {
                values: input,
                mode: mode,
                tableName: this.options.tableName,
                pageId: this.options.pageId,
                parentPageId: this.options.parentPageId,
                recordId: this.options.recordId,
                language: this.options.language,
                fieldName: this.options.fieldName,
                command: this.options.command,
                signature: this.options.signature,
            }, function (response) {
                var visualProposal = '/' + response.proposal.replace(/^\//, '');
                var proposal = response.proposal;
                if (response.inaccessibleSegments) {
                    proposal = proposal.substring(response.inaccessibleSegments.length);
                }
                if (response.lastSegmentOnly) {
                    proposal = proposal.substring(proposal.lastIndexOf("/"));
                }
                if (response.hasConflicts) {
                    _this.$fullElement.find('.t3js-form-proposal-accepted').addClass('hidden');
                    _this.$fullElement.find('.t3js-form-proposal-different').removeClass('hidden').find('span').text(visualProposal);
                }
                else {
                    _this.$fullElement.find('.t3js-form-proposal-accepted').removeClass('hidden').find('span').text(visualProposal);
                    _this.$fullElement.find('.t3js-form-proposal-different').addClass('hidden');
                }
                var isChanged = _this.$hiddenField.val() !== proposal;
                if (isChanged) {
                    _this.$fullElement.find('input').trigger('change');
                }
                if (mode === ProposalModes.AUTO || mode === ProposalModes.RECREATE) {
                    _this.$readOnlyField.val(proposal);
                    _this.$hiddenField.val(proposal);
                    _this.$inputField.val(proposal);
                }
                else {
                    _this.$hiddenField.val(proposal);
                }
            }, 'json');
        };
        /**
         * Gets a list of all available fields that can be used for slug generation
         *
         * @return { [key: string]: string }
         */
        SlugElement.prototype.getAvailableFieldsForProposalGeneration = function () {
            var availableFields = {};
            $.each(this.fieldsToListenOn, function (fieldName, field) {
                var $selector = $('[data-formengine-input-name="' + field + '"]');
                if ($selector.length > 0) {
                    availableFields[fieldName] = field;
                }
            });
            return availableFields;
        };
        return SlugElement;
    }());
    return SlugElement;
});
