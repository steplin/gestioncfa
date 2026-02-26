import './styles/app.scss';
import tinymce from "tinymce";
import "tinymce/themes/silver";
import "tinymce/icons/default";

import "tinymce/plugins/lists";
import "tinymce/plugins/link";
import "tinymce/plugins/table";
import "tinymce/plugins/code";
import "select2/dist/css/select2.css";
import 'select2';


require('bootstrap');
import $ from 'jquery';
window.$ = window.jQuery = $;

require('bootstrap');
// CSS Bootstrap 3


import 'select2';

$('.select2').select2();

// seance edit
// =====================================
// MODAL AJAX GENERIQUE REUTILISABLE
// =====================================

function initSelect2(context = document) {
    if (typeof $.fn.select2 !== 'undefined') {
        $(context).find('.select2').select2({
            width: '100%',
            dropdownParent: $('#ajaxModal')
        });
    }
}

$(document).on('click', '.open-ajax-modal', function () {

    const url = $(this).data('url');
    const title = $(this).data('title') || 'Edition';

    $('#ajaxModalTitle').text(title);
    $('#ajaxModalBody').html(
        '<div class="text-center"><span class="glyphicon glyphicon-refresh glyphicon-spin"></span></div>'
    );

    $('#ajaxModal').modal('show');

    $.get(url, function (data) {
        $('#ajaxModalBody').html(data);

        // réinit composants dynamiques
        initSelect2('#ajaxModalBody');
    });
});


$(document).on('submit', '#ajaxModal form', function (e) {
    e.preventDefault();

    const form = $(this);

    $.post(form.attr('action'), form.serialize(), function (response) {

        if (response.success) {
            $('#ajaxModal').modal('hide');
            location.reload(); // ou callback custom plus tard
        } else {
            $('#ajaxModalBody').html(response);
            initSelect2('#ajaxModalBody');
        }

    });
});
function reloadGroupesForClasse($classeSelect) {
    const classeId = $classeSelect.val();
    const urlTpl = $classeSelect.data('groupes-url'); // .../ajax/classe/0/groupes

    const $groupeSelect = $('#ajaxModal').find('select[name$="[groupe]"]');
    if (!$groupeSelect.length) return;

    $groupeSelect.empty().append(new Option('Sélectionner un groupe', '', true, true));

    if (!classeId) {
        $groupeSelect.trigger('change');
        return;
    }

    const url = urlTpl.replace('/0/', '/' + classeId + '/');

    $.getJSON(url, function(items) {
        items.forEach(it => $groupeSelect.append(new Option(it.nom, it.id, false, false)));

        // reinit select2 sur le groupe
        if ($groupeSelect.data('select2')) $groupeSelect.select2('destroy');
        initSelect2('#ajaxModal');

        $groupeSelect.trigger('change');
    });
}

// Quand la modal est ouverte et que le form est injecté :
$(document).on('shown.bs.modal', '#ajaxModal', function () {
    initSelect2('#ajaxModal');

    const $classeSelect = $('#ajaxModal').find('.js-classe-select');
    if ($classeSelect.length) {
        // si une classe est déjà sélectionnée (édition), charge la liste groupes
        reloadGroupesForClasse($classeSelect);
    }
});

// Changement de classe dans la modal
$(document).on('change', '#ajaxModal .js-classe-select', function () {
    reloadGroupesForClasse($(this));
});
// =====================================
// DELETE AJAX GENERIQUE
// =====================================

$(document).on('click', '.delete-entity', function () {

    const url = $(this).data('url');

    if (!confirm('Confirmer la suppression ?')) {
        return;
    }

    $.post(url, function (response) {

        if (response.success) {
            $('#ajaxModal').modal('hide');
            location.reload();
        }

    });

});
