(function ($, Drupal) {

  'use strict';

  /**
   * Ajax command for reloading the window.
   *
   * @param {Drupal.Ajax} [ajax]
   *   An Ajax object.
   * @param {object} response
   *   The Ajax response.
   * @param {string} response.selector
   *   The selector in question.
   * @param {number} [status]
   *   The HTTP status code.
   */
  Drupal.AjaxCommands.prototype.reload = function (ajax, response, status) {
    window.location.reload();
  };

})(jQuery, Drupal);
