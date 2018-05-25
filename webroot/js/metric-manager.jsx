import React from 'react';
import 'jstree';
import '../css/metric-manager.scss';
import ReactDom from 'react-dom';
import 'bootstrap';
import {Button} from 'reactstrap';
import {MetricModal} from './metric-modal.jsx';
import fontawesome from '@fortawesome/fontawesome';
require('@fortawesome/fontawesome-free-solid');
require('@fortawesome/fontawesome-free-regular');

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
      let newName = node.text;
      let originalName = node.original.text;
      let metricId = node.data.metricId;

      if (cancelled) {
        return;
      }

      if (!status) {
        alert('Error renaming metric: ' + jstree.last_error());
        return;
      }

      MetricManager.showNodeUpdateLoading(jstree, node);

      $.ajax({
        method: 'PATCH',
        url: '/api/metrics/rename.json',
        dataType: 'json',
        data: {
          'context': context,
          'metricId': metricId,
          'newName': newName,
        },
      }).done(function(data) {
        if (data.hasOwnProperty('result') && data.result) {
          return;
        }

        // Display error and undo the renaming of this node
        alert(data.message);
        jstree.rename_node(node, originalName);
      }).fail(function(jqXHR, errorType, exception) {
        console.log(jqXHR);
        console.log(errorType);
        console.log(exception);

        // Undo the renaming of this node
        jstree.rename_node(node, originalName);
      }).always(() => {
        MetricManager.showNodeUpdateComplete(jstree, node);
      });
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
    const context = window.metricManager.context;
    let metricId = node.data.metricId;

    if (jstree.is_parent(node)) {
      alert('Cannot delete a metric that has children');
      return;
    }

    MetricManager.showNodeUpdateLoading(jstree, node);

    $.ajax({
      method: 'DELETE',
      url: '/api/metrics/delete/' + context + '/' + metricId + '.json',
      dataType: 'json',
    }).done((data) => {
      if (data.hasOwnProperty('result') && data.result) {
        jstree.delete_node(node);
        return;
      }
      alert('There was an error deleting that metric');
    }).fail(function(jqXHR, errorType, exception) {
      console.log(jqXHR);
      console.log(errorType);
      console.log(exception);
      alert('There was an error deleting that metric');
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
      this.setState({
        hasError: true,
        errorMsg: jqXHR.responseJSON.message,
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
      'data': MetricManager.formatMetricsForJsTree(data.metrics),
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
                const jstree = $('#jstree').jstree();
                const node = jstree.get_node(data.reference);
                window.jsTreeData.editMetric = node.data;
                window.jsTreeData.editMetricNode = node;
                this.handleEditModalOpen();
              },
            },
            'Delete': {
              'label': 'Delete',
              'title': 'Delete this metric',
              'action': (data) => {
                this.handleDelete(data);
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
    const jstree = $('#jstree').jstree();
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

  static formatMetricsForJsTree(data) {
    let retval = [];

    data.forEach((metric) => {
      let jTreeData = {
        text: metric.name,
        data: {
          selectable: Boolean(metric.selectable),
          type: metric.type,
          metricId: metric.id,
          name: metric.name,
          description: metric.description,
        },
        li_attr: {
          'data-selectable': Boolean(metric.selectable) ? 1 : 0,
          'data-type': metric.type,
        },
        icon: Boolean(metric.selectable) ? 'far fa-check-circle' : 'fas fa-ban',
      };
      if (metric.children.length > 0) {
        jTreeData.children = MetricManager.formatMetricsForJsTree(
            metric.children
        );
      }
      retval.push(jTreeData);
    });

    return retval;
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
          <Button color="outline-primary"
                  onClick={this.handleCreateModalRootOpen}
                  ref={this.submitButton}>
            Add root-level metric
          </Button>
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
      </div>
    );
  }
}

ReactDom.render(
  <MetricManager />,
  document.getElementById('metric-manager')
);
