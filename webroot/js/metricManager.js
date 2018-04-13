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
            let img = $('<img src="/jstree/themes/default/throbber.gif" />');
            img.attr('alt', 'Loading...');
            img.addClass('loading');
            liElement.find('a.jstree-anchor').append(img);
            inst.disable_node(obj);
          },
          success: function(data) {
            if (data.hasOwnProperty('result') && data.result) {
              console.log('Success');
            } else {
              alert(data.message);
              inst.rename_node(obj, originalName);
            }
          },
          error: function(jqXHR, errorType, exception) {
            console.log(jqXHR);
            console.log(errorType);
            console.log(exception);
            inst.rename_node(obj, originalName);
          },
          complete: function() {
            liElement.find('img.loading').remove();
            inst.enable_node(obj);
          },
        });
      });
    },
  },

  createConfig: {
    'label': 'Create',
    'title': 'Create a metric in this group',
    'action': function(data) {
      console.log('Create');
      console.log(data);
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
          let img = $('<img src="/jstree/themes/default/throbber.gif" />');
          img.attr('alt', 'Loading...');
          img.addClass('loading');
          liElement.find('a.jstree-anchor').append(img);
          inst.disable_node(obj);
        },
        success: function(data) {
          if (data.hasOwnProperty('result') && data.result) {
            console.log('Success');
            inst.delete_node(obj);
            return;
          }
          alert('There was an error deleting that node');
        },
        error: function(jqXHR, errorType, exception) {
          console.log(jqXHR);
          console.log(errorType);
          console.log(exception);
          alert('There was an error deleting that node');
        },
        complete: function() {
          liElement.find('img.loading').remove();
          inst.enable_node(obj);
        },
      });
    },
  },
};
