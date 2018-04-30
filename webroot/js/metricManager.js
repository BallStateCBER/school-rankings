import 'jstree';
import '../css/metric-manager.scss';

let beforeSend = function(liElement, jstree, node) {
  let img = $('<img src="/jstree/themes/default/throbber.gif" />');
  img.attr('alt', 'Loading...');
  img.addClass('loading');
  liElement.find('a.jstree-anchor').append(img);
  jstree.disable_node(node);
};

let onComplete = function(liElement, jstree, node) {
  liElement.find('img.loading').remove();
  jstree.enable_node(node);
};

let createConfig = {
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
};

let renameConfig = {
  'label': 'Rename',
  'title': 'Rename this metric',
  'action': function(data) {
    let jstree = $.jstree.reference(data.reference);
    let node = jstree.get_node(data.reference);
    let context = $('#' + node.id).closest('section').data('context');
    jstree.edit(node, null, function(node, status, cancelled) {
      let newName = node.text;
      let originalName = node.original.text;
      let metricId = node.data.metricId;
      let liElement = $('#' + node.id);

      if (cancelled) {
        return;
      }

      if (!status) {
        alert('Error renaming metric: ' + jstree.last_error());
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
          beforeSend(liElement, jstree, node);
        },
        success: function(data) {
          if (data.hasOwnProperty('result') && data.result) {
            return;
          }

          alert(data.message);
          jstree.rename_node(node, originalName);
        },
        error: function(jqXHR, errorType, exception) {
          console.log(jqXHR);
          console.log(errorType);
          console.log(exception);
          jstree.rename_node(node, originalName);
        },
        complete: function() {
          onComplete(liElement, jstree, node);
        },
      });
    });
  },
};

let deleteConfig = {
  'label': 'Delete',
  'title': 'Delete this metric',
  'action': function(data) {
    let jstree = $.jstree.reference(data.reference);
    let node = jstree.get_node(data.reference);
    let liElement = $('#' + node.id);
    let context = liElement.closest('section').data('context');
    let metricId = node.data.metricId;

    if (jstree.is_parent(node)) {
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
        beforeSend(liElement, jstree, node);
      },
      success: function(data) {
        if (data.hasOwnProperty('result') && data.result) {
          jstree.delete_node(node);
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
        onComplete(liElement, jstree, node);
      },
    });
  },
};

/**
 * Reformats metric data for JsTree
 *
 * @param {Object} data
 * @return {Array}
 */
function formatMetricsForJsTree(data) {
  let retval = [];

  data.forEach(function(metric) {
    let jTreeData = {
      text: metric.name,
      a_attr: {
        selectable: metric.selectable ? 1 : 0,
        type: metric.type,
      },
      data: {
        selectable: Boolean(metric.selectable),
        type: metric.type,
        metricId: metric.id,
      },
    };
    if (metric.children.length > 0) {
      jTreeData.children = formatMetricsForJsTree(metric.children);
    }
    retval.push(jTreeData);
  });

  return retval;
}

/**
 * Initializes the JsTree for the specified container and context
 * @param {string} containerSelector
 * @param {string} context
 */
function setupJsTree(containerSelector, context) {
  let container = $(containerSelector);
  $.ajax({
    method: 'GET',
    url: '/api/metrics/' + context + 's.json',
    dataType: 'json',
    beforeSend: function() {
      let span = $('<span>Loading data...</span>');
      span.addClass('loading');
      container.append(span);
      let img = $('<img src="/jstree/themes/default/throbber.gif" />');
      img.attr('alt', 'Loading...');
      img.addClass('loading');
      container.append(img);
    },
    success: function(data) {
      container.jstree({
        'core': {
          'data': formatMetricsForJsTree(data.metrics),
          'check_callback': true,
        },
        'plugins': [
          'contextmenu',
          'dnd',
          'sort',
          'state',
          'wholerow',
        ],
        'contextmenu': {
          'items': function() {
            return {
              'Create': createConfig,
              'Rename': renameConfig,
              'Delete': deleteConfig,
            };
          },
        },
      });
    },
    error: function(jqXHR) {
      const msg = jqXHR.responseJSON.message;
      const error = '<p class="text-danger">' + msg + '</p>';
      container.append(error);
    },
    complete: function() {
      container.find('loading').remove();
    },
  });
}

$(document).ready(function() {
  setupJsTree('#school-metrics-tree', 'school');
  setupJsTree('#district-metrics-tree', 'district');
});
