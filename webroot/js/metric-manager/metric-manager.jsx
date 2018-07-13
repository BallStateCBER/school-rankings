import React from 'react';
import 'jstree';
import '../../css/metric-manager.scss';
import ReactDom from 'react-dom';
import 'bootstrap';
import {Button} from 'reactstrap';
import {MetricModal} from './metric-modal.jsx';
import fontawesome from '@fortawesome/fontawesome';
require('@fortawesome/fontawesome-free-solid');
require('@fortawesome/fontawesome-free-regular');
import {Legend} from './legend.jsx';
import {Formatter} from './formatter.js';

window.jsTreeData = {
  createMetric: {},
  editMetric: {},
};

fontawesome.config.searchPseudoElements = true;

class MetricManager extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      loading: false,
      hasError: false,
      errorMsg: '',
      openCreateModal: false,
      openEditModal: false,
    };
    this.componentDidMount = this.componentDidMount.bind(this);
    this.handleCreateModalOpen = this.handleCreateModalOpen.bind(this);
    this.handleCreateModalRootOpen = this.handleCreateModalRootOpen.bind(this);
    this.handleCreateModalClose = this.handleCreateModalClose.bind(this);
    this.handleEditModalOpen = this.handleEditModalOpen.bind(this);
    this.handleEditModalClose = this.handleEditModalClose.bind(this);
  }

  handleCreateModalRootOpen() {
    window.jsTreeData.createMetric = {root: true};
    this.handleCreateModalOpen();
  }

  handleCreateModalOpen() {
    this.setState({openCreateModal: true});
  }

  handleCreateModalClose() {
    this.setState({openCreateModal: false});
  }

  handleRename(data) {
    let jstree = $.jstree.reference(data.reference);
    let node = jstree.get_node(data.reference);
    const context = window.metricManager.context;
    jstree.edit(node, null, (node, status, cancelled) => {
      if (cancelled) {
        return;
      }

      if (!status) {
        alert('Error renaming metric: ' + jstree.last_error());
        return;
      }

      const newName = node.text;
      const metricId = node.data.metricId;
      const requestData = {
        'context': context,
        'metricId': metricId,
        'name': newName,
      };
      this.sendEditRequest(metricId, requestData, jstree, node);
    });
  }

  handleToggleSelectable(data) {
    let jstree = $.jstree.reference(data.reference);
    let node = jstree.get_node(data.reference);
    const selectable = node.data.selectable;
    const context = window.metricManager.context;
    let metricId = node.data.metricId;
    const requestData = {
      'context': context,
      'metricId': metricId,
      'selectable': !selectable,
    };

    this.sendEditRequest(metricId, requestData, jstree, node);
  }

  handleToggleType(data) {
    let jstree = $.jstree.reference(data.reference);
    let node = jstree.get_node(data.reference);
    const newType = node.data.type === 'boolean' ? 'numeric' : 'boolean';
    const context = window.metricManager.context;
    const metricId = node.data.metricId;
    const requestData = {
      'context': context,
      'metricId': metricId,
      'type': newType,
    };

    this.sendEditRequest(metricId, requestData, jstree, node);
  }

  handleToggleVisible(data) {
    let jstree = $.jstree.reference(data.reference);
    let node = jstree.get_node(data.reference);
    const newVisible = !node.data.visible;
    const context = window.metricManager.context;
    const metricId = node.data.metricId;
    const requestData = {
      'context': context,
      'metricId': metricId,
      'visible': newVisible,
    };

    this.sendEditRequest(metricId, requestData, jstree, node);
  }

  sendEditRequest(metricId, requestData, jstree, node) {
    MetricManager.showNodeUpdateLoading(jstree, node);

    const handleError = function(msg) {
      // Undo renaming of node
      if (requestData.hasOwnProperty('name')) {
        jstree.rename_node(node, node.original.text);
      }

      if (! msg) {
        msg = 'Error updating metric';
      }

      alert(msg);
    };

    $.ajax({
      method: 'PATCH',
      url: '/api/metrics/edit/' + metricId + '.json',
      dataType: 'json',
      data: requestData,
    }).done(function(data) {
      if (data.hasOwnProperty('result') && data.result) {
        MetricManager.updateNode(node, requestData);
        return;
      }

      let msg = null;
      if (data.hasOwnProperty('message')) {
        msg = data.message;
      }

      handleError(msg);
    }).fail(function(jqXHR, errorType, exception) {
      console.log(jqXHR);
      console.log(errorType);
      console.log(exception);

      let msg = null;
      if (jqXHR.hasOwnProperty('responseJSON')) {
        if (jqXHR.responseJSON.hasOwnProperty('message')) {
          msg = jqXHR.responseJSON.message;
        }
      }

      handleError(msg);
    }).always(() => {
      MetricManager.showNodeUpdateComplete(jstree, node);
    });
  }

  handleEditModalOpen() {
    this.setState({openEditModal: true});
  }

  handleEditModalClose() {
    this.setState({openEditModal: false});
  }

  handleDelete(data) {
    let jstree = $.jstree.reference(data.reference);
    let node = jstree.get_node(data.reference);
    let metricId = node.data.metricId;

    if (jstree.is_parent(node)) {
      alert('Cannot delete a metric that has children');
      return;
    }

    MetricManager.showNodeUpdateLoading(jstree, node);

    const handleError = function(msg) {
      if (! msg) {
        msg = 'There was an error deleting that metric';
      }

      alert(msg);
    };

    $.ajax({
      method: 'DELETE',
      url: '/api/metrics/delete/' + metricId + '.json',
      dataType: 'json',
    }).done((data) => {
      if (data.hasOwnProperty('result') && data.result) {
        jstree.delete_node(node);
        return;
      }

      let msg = null;
      if (data.hasOwnProperty('message')) {
        msg = data.message;
      }

      handleError(msg);
    }).fail(function(jqXHR, errorType, exception) {
      console.log(jqXHR);
      console.log(errorType);
      console.log(exception);

      let msg = null;
      if (jqXHR.hasOwnProperty('responseJSON')) {
        if (jqXHR.responseJSON.hasOwnProperty('message')) {
          msg = jqXHR.responseJSON.message;
        }
      }

      handleError(msg);
    }).always(() => {
      MetricManager.showNodeUpdateComplete(jstree, node);
    });
  }

  componentDidMount() {
    this.setState({loading: true});
    const context = window.metricManager.context;

    $.ajax({
      method: 'GET',
      url: '/api/metrics/' + context + 's.json',
      dataType: 'json',
    }).done((data) => {
      $('#jstree').jstree(this.getJsTreeConfig(data));
    }).fail((jqXHR) => {
      let errorMsg = 'Error loading metrics';
      if (jqXHR.hasOwnProperty('responseJSON')) {
        if (jqXHR.responseJSON.hasOwnProperty('message')) {
          errorMsg = jqXHR.responseJSON.message;
        }
      }
      this.setState({
        hasError: true,
        errorMsg: errorMsg,
      });
    }).always(() => {
      this.setState({loading: false});
      $(document).on('dnd_stop.vakata', (event, data) => {
        this.handleNodeDrop(data);
      });
    });
  }

  getJsTreeConfig(data) {
    return {
      'core': {
        'data': Formatter.formatMetricsForJsTree(data.metrics),
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
        'items': () => {
          return {
            'Create': {
              'label': 'Create',
              'title': 'Create a metric in the selected group',
              'action': (data) => {
                window.jsTreeData.createMetric = data;
                this.handleCreateModalOpen();
              },
            },
            'Rename': {
              'label': 'Rename',
              'title': 'Rename this metric',
              'action': (data) => {
                this.handleRename(data);
              },
            },
            'Edit': {
              'label': 'Edit',
              'title': 'Edit this metric',
              'action': (data) => {
                const jstree = $('#jstree').jstree(true);
                const node = jstree.get_node(data.reference);
                window.jsTreeData.editMetric = node.data;
                window.jsTreeData.editMetricNode = node;
                this.handleEditModalOpen();
              },
            },
            'Toggle submenu': {
              'label': 'Toggle...',
              'submenu': {
                'Toggle selectable': {
                  'label': 'Selectable',
                  'title': 'Toggle ability to select this metric for rankings',
                  'action': (data) => {
                    this.handleToggleSelectable(data);
                  },
                },
                'Toggle type': {
                  'label': 'Numeric/boolean',
                  'title': 'Toggle data type between numeric and boolean',
                  'action': (data) => {
                    this.handleToggleType(data);
                  },
                },
                'Toggle visible': {
                  'label': 'Visible',
                  'title': 'Toggle metric visibility for regular users',
                  'action': (data) => {
                    this.handleToggleVisible(data);
                  },
                },
              },
            },
            'Delete': {
              'label': 'Delete',
              'title': 'Delete this metric',
              'action': (data) => {
                if (confirm('Delete metric and all associated data?')) {
                  this.handleDelete(data);
                }
              },
            },
          };
        },
      },
      'dnd': {
        'inside_pos': 'last',
        'large_drop_target': true,
        'large_drag_target': true,
        'use_html5': true,
      },
    };
  }

  handleNodeDrop(data) {
    const jstree = $('#jstree').jstree(true);
    const draggedNode = jstree.get_node(data.element);
    const movedMetricId = draggedNode.data.metricId;
    const parentNodeId = jstree.get_parent(draggedNode);
    const parentNode = jstree.get_node(parentNodeId);
    const parentMetricId = parentNode.hasOwnProperty('data')
        ? parentNode.data.metricId
        : null;
    const context = window.metricManager.context;

    MetricManager.showNodeUpdateLoading(jstree, draggedNode);

    $.ajax({
      method: 'PATCH',
      url: '/api/metrics/reparent.json',
      dataType: 'json',
      data: {
        'metricId': movedMetricId,
        'context': context,
        'newParentId': parentMetricId,
      },
    }).done((data) => {
      if (data.hasOwnProperty('result') && data.result) {
        return;
      }
      alert('There was an error moving that metric. ' +
          'Check console for details and refresh.');
      this.forceUpdate();
      console.log(data.message);
    }).fail((jqXHR, errorType, exception) => {
      console.log(jqXHR);
      console.log(errorType);
      console.log(exception);
      alert('There was an error moving that metric. ' +
          'Check console for details and refresh.');
      this.forceUpdate();
    }).always(() => {
      MetricManager.showNodeUpdateComplete(jstree, draggedNode);
    });
  }

  static showNodeUpdateLoading(jstree, node) {
    let liElement = $('#' + node.id);
    let img = $('<img src="/jstree/themes/default/throbber.gif" />');
    img.attr('alt', 'Loading...');
    img.addClass('loading');
    liElement.find('a.jstree-anchor').append(img);
    jstree.disable_node(node);
  };

  static showNodeUpdateComplete(jstree, node) {
    let liElement = $('#' + node.id);
    liElement.find('img.loading').remove();
    jstree.enable_node(node);
  };

  static updateNode(node, data) {
    if (data.hasOwnProperty('name')) {
      node.text = data.name;
    }
    if (data.hasOwnProperty('selectable')) {
      node.li_attr['data-selectable'] = data.selectable;
      node.icon = data.selectable ? 'far fa-check-circle' : 'fas fa-ban';
    }
    if (data.hasOwnProperty('type')) {
      node.li_attr['data-type'] = data.type;
    }
    if (data.hasOwnProperty('visible')) {
      node.li_attr['data-visible'] = data.visible ? 1 : 0;
    }
    for (let property in data) {
      if ({}.hasOwnProperty.call(data, property)) {
        node.data[property] = data[property];
      }
    }

    $('#jstree').jstree(true).redraw(true);
  }

  static toggleMetricIds() {
    $('#metric-manager').toggleClass('show-metric-ids');
  }

  render() {
    return (
      <div>
        {this.state.loading &&
          <span className="loading">
            Loading data...
            <img src="/jstree/themes/default/throbber.gif" alt="Loading..."
                 className="loading"/>
          </span>
        }
        {! this.state.loading &&
          <div>
            <Button color="outline-primary"
                    onClick={this.handleCreateModalRootOpen}
                    ref={this.submitButton}>
              Add root-level metric
            </Button>
            {' '}
            <Button color="outline-primary"
                    onClick={MetricManager.toggleMetricIds}>
              Show metric IDs
            </Button>
          </div>
        }
        {this.state.hasError &&
          <p className="text-danger">{this.state.errorMsg}</p>
        }
        <div id="jstree"></div>
        <MetricModal onClose={this.handleCreateModalClose}
               isOpen={this.state.openCreateModal} mode="add" />
        {this.state.openEditModal &&
          <MetricModal onClose={this.handleEditModalClose}
                 isOpen={this.state.openEditModal} mode="edit"/>
        }
        {! this.state.loading &&
          <Legend/>
        }
      </div>
    );
  }
}

export {MetricManager};

ReactDom.render(
  <MetricManager />,
  document.getElementById('metric-manager')
);
