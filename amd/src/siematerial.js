// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version details.
 *
 * @package    block_siematerial
 * @copyright  2015 Planificacion de Entornos Tecnologicos SL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module block_siematerial/siematerial
 */
define(['jquery'], function($) {
    var block_siematerial_userid = -1;

    /**
     * Confirms with the user to delete a file or not
     * @param fileid File identifier
     */
    function confirm_deletion(fileid) {
        var askquestion = $('#askquestion').val();
        var dialog = confirm(askquestion);
        if (dialog === true) {
            var form = document.getElementById('coursefileform');
            document.getElementById('action').value = 'delete';
            document.getElementById('file_id').value = fileid;
            form.submit();
        }
    }

    return {
        confirm_deletion: confirm_deletion,
        init: function (userid) {
            // Do nothing.
            block_siematerial_userid = userid;
        }
    };
});