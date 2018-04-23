metricManager = {
  contextMenuConfig: {
    'items': function() {
      return {
        'Create': metricManager.createConfig,
        'Rename': metricManager.renameConfig,
        'Delete': metricManager.deleteConfig,
      };
    },
  },

  renameConfig: {
    'label': 'Rename',
    'title': 'Rename this metric',
    'action': function(data) {
      let inst = $.jstree.reference(data.reference);
      let obj = inst.get_node(data.reference);
      let context = $('#' + obj.id).closest('section').data('context');
      inst.edit(obj, null, function(node, status, cancelled) {
        let newName = node.text;
        let originalName = node.original.text;
        let metricId = node.data.metricId;
        let liElement = $('#' + obj.id);

        if (cancelled) {
          return;
        }

        if (!status) {
          alert('Error renaming metric: ' + inst.last_error());
          return;
        }

        $.ajax({
          method: 'PATCH',
          url: '/admin/metrics/rename.json',
          dataType: 'json',
          data: {
            'context': context,
            'metricId': metricId,
            'newName': newName,
          },
          beforeSend: function() {
            metricManager.beforeSend(liElement, inst, obj);
          },
          success: function(data) {
            if (data.hasOwnProperty('result') && data.result) {
              return;
            }

            alert(data.message);
            inst.rename_node(obj, originalName);
          },
          error: function(jqXHR, errorType, exception) {
            console.log(jqXHR);
            console.log(errorType);
            console.log(exception);
            inst.rename_node(obj, originalName);
          },
          complete: function() {
            metricManager.onComplete(liElement, inst, obj);
          },
        });
      });
    },
  },

  createConfig: {
    'label': 'Create',
    'title': 'Create a metric in the selected group',
    'action': function(data) {
      let jstree = $.jstree.reference(data.reference);
      let parentNode = jstree.get_node(data.reference);
      let liElement = $('#' + parentNode.id);
      let context = liElement.closest('section').data('context');
      let metricId = parentNode.data.metricId;
      let blankModal = $('#add-modal');
      const timestamp = (new Date()).getTime();
      const newId = blankModal.attr('id') + '-' + timestamp;
      let modal = blankModal.clone().attr('id', newId);

      modal.modal('show');
      modal.find('form').submit(function(event) {
        event.preventDefault();
        let button = modal.find('button[type=submit]');
        const metricName = modal.find('input[type=text]').val().trim();
        const description = modal.find('textarea').val().trim();
        const type = modal.find('input[type=radio]:checked').val();
        const selectable = modal.find('input[type=checkbox]').is(':checked');

        $.ajax({
          method: 'POST',
          url: '/admin/metrics/add.json',
          dataType: 'json',
          data: {
            'context': context,
            'parentId': metricId,
            'name': metricName,
            'description': description,
            'type': type,
            'selectable': selectable,
          },
          beforeSend: function() {
            button.prop('disabled', true);
            let img = $('<img src="/jstree/themes/default/throbber.gif" />');
            img.attr('alt', 'Loading...');
            button.append(img);
          },
          success: function() {
            jstree.create_node(parentNode, metricName);
            modal.modal('hide').data('bs.modal', null);
          },
          error: function(jqXHR) {
            const msg = jqXHR.responseJSON.message;
            const error = '<p class="text-danger">' + msg + '</p>';
            let body = modal.find('.modal-body');
            body.find('.text-danger').remove();
            body.append(error);
            button.prop('disabled', false);
            button.find('img').remove();
          },
        });
      });
    },
  },

  deleteConfig: {
    'label': 'Delete',
    'title': 'Delete this metric',
    'action': function(data) {
      let inst = $.jstree.reference(data.reference);
      let obj = inst.get_node(data.reference);
      let liElement = $('#' + obj.id);
      let context = liElement.closest('section').data('context');
      let metricId = obj.data.metricId;

      if (inst.is_parent(obj)) {
        alert('Cannot delete a metric that has children');
        return;
      }

      $.ajax({
        method: 'DELETE',
        url: '/admin/metrics/delete.json',
        dataType: 'json',
        data: {
          'context': context,
          'metricId': metricId,
        },
        beforeSend: function() {
          metricManager.beforeSend(liElement, inst, obj);
        },
        success: function(data) {
          if (data.hasOwnProperty('result') && data.result) {
            inst.delete_node(obj);
            return;
          }
          alert('There was an error deleting that metric');
        },
        error: function(jqXHR, errorType, exception) {
          console.log(jqXHR);
          console.log(errorType);
          console.log(exception);
          alert('There was an error deleting that metric');
        },
        complete: function() {
          metricManager.onComplete(liElement, inst, obj);
        },
      });
    },
  },

  beforeSend: function(liElement, inst, obj) {
    let img = $('<img src="/jstree/themes/default/throbber.gif" />');
    img.attr('alt', 'Loading...');
    img.addClass('loading');
    liElement.find('a.jstree-anchor').append(img);
    inst.disable_node(obj);
  },

  onComplete: function(liElement, inst, obj) {
    liElement.find('img.loading').remove();
    inst.enable_node(obj);
  },
};
