import React from 'react';
import 'jstree';
import '../css/metric-manager.scss';
import ReactDom from 'react-dom';
import 'bootstrap';
import {Button, Modal, ModalHeader, ModalBody, ModalFooter} from 'reactstrap';

window.jsTreeData = [];

class CreateModal extends React.Component {
  constructor(props) {
    super(props);

    this.close = this.close.bind(this);
    this.submit = this.submit.bind(this);
    this.handleChange = this.handleChange.bind(this);

    this.submitButton = React.createRef();

    this.state = {
      metricName: '',
      metricDescription: '',
      metricSelectable: true,
      metricType: 'numeric',
      submitInProgress: false,
    };
  }

  handleChange(event) {
    const target = event.target;
    const name = target.name;
    const value = target.type === 'checkbox' ? target.checked : target.value;

    this.setState({[name]: value});
  }

  close() {
    this.props.onClose();
    this.setState({
      metricName: '',
      metricDescription: '',
      metricSelectable: true,
      metricType: 'numeric',
    });
  }

  submit(event) {
    event.preventDefault();

    this.setState({submitInProgress: true});

    const data = window.jsTreeData.createMetric;
    const isRoot = data.hasOwnProperty('root') && data.root;
    const jstree = $('#jstree').jstree();
    const parentNode = isRoot ? null : jstree.get_node(data.reference);
    const parentId = isRoot ? null : parentNode.data.metricId;
    const metricName = this.state.metricName.trim();
    const submitData = {
      'context': window.metricManager.context,
      'parentId': parentId,
      'name': metricName,
      'description': this.state.metricDescription.trim(),
      'type': this.state.metricType,
      'selectable': this.state.metricSelectable,
    };

    $.ajax({
      method: 'POST',
      url: '/api/metrics/add.json',
      dataType: 'json',
      data: submitData,
    }).done(() => {
      jstree.create_node(parentNode, metricName);
      this.close();
    }).fail((jqXHR) => {
      const msg = jqXHR.responseJSON.message;
      alert(msg);
      this.setState({submitInProgress: false});
    });
  }

  render() {
    return (
      <Modal isOpen={this.props.isOpen} toggle={this.close}
             className={this.props.className}
             ref={(modal) => this.modal = modal}>
        <ModalHeader toggle={this.close}>Add metric</ModalHeader>
        <form onSubmit={this.submit}>
          <ModalBody>
            <fieldset className="form-group">
              <label htmlFor="metric-name">Name:</label>
              <input type="text" className="form-control"
                     required="required" onChange={this.handleChange}
                     name="metricName" value={this.state.metricName} />
            </fieldset>
            <fieldset className="form-group">
              <label htmlFor="metric-description">Description:</label>
              <textarea className="form-control"
                        rows="3" onChange={this.handleChange}
                        name="metricDescription"
                        value={this.state.metricDescription}></textarea>
            </fieldset>
            <fieldset className="form-group">
              <div className="form-check">
                <input className="form-check-input" type="radio"
                       name="metricType" id="numeric-radio" value="numeric"
                       checked={this.state.metricType === 'numeric'}
                       onChange={this.handleChange} />
                <label className="form-check-label"
                       htmlFor="numeric-radio">
                  Numeric
                </label>
              </div>
              <div className="form-check">
                <input className="form-check-input" type="radio"
                       name="metricType" id="boolean-radio" value="boolean"
                       checked={this.state.metricType === 'boolean'}
                       onChange={this.handleChange} />
                <label className="form-check-label"
                       htmlFor="boolean-radio">
                  Boolean
                </label>
              </div>
            </fieldset>
            <fieldset className="form-group">
              <div className="form-check">
                <input className="form-check-input" type="checkbox"
                       value="1" id="selectable-checkbox"
                       name="metricSelectable"
                       checked={this.state.metricSelectable}
                       onChange={this.handleChange} />
                <label className="form-check-label"
                       htmlFor="selectable-checkbox">
                  Selectable
                </label>
              </div>
            </fieldset>
          </ModalBody>
          <ModalFooter>
            <Button color="primary" onClick={this.submit}
                    ref={this.submitButton}
                    disabled={this.state.submitInProgress}>
              Add
            </Button>
            {' '}
            <Button color="secondary" onClick={this.close}
                    data-dismiss="modal">Cancel</Button>
          </ModalFooter>
        </form>
      </Modal>
    );
  }
}

CreateModal.propTypes = Modal.propTypes;

let showNodeUpdateLoading = function(jstree, node) {
  let liElement = $('#' + node.id);
  let img = $('<img src="/jstree/themes/default/throbber.gif" />');
  img.attr('alt', 'Loading...');
  img.addClass('loading');
  liElement.find('a.jstree-anchor').append(img);
  jstree.disable_node(node);
};

let showNodeUpdateComplete = function(jstree, node) {
  let liElement = $('#' + node.id);
  liElement.find('img.loading').remove();
  jstree.enable_node(node);
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

class MetricManager extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      loading: false,
      hasError: false,
      errorMsg: '',
      openCreateModal: false,
    };
    this.componentDidMount = this.componentDidMount.bind(this);
    this.handleCreateModalOpen = this.handleCreateModalOpen.bind(this);
    this.handleCreateModalClose = this.handleCreateModalClose.bind(this);
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
    jstree.edit(node, null, function(node, status, cancelled) {
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

      showNodeUpdateLoading(jstree, node);

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
        alert(data.message);
        jstree.rename_node(node, originalName);
      }).fail(function(jqXHR, errorType, exception) {
        console.log(jqXHR);
        console.log(errorType);
        console.log(exception);
        jstree.rename_node(node, originalName);
      }).always(function() {
        showNodeUpdateComplete(jstree, node);
      });
    });
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

    showNodeUpdateLoading(jstree, node);

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
    }).always(function() {
      showNodeUpdateComplete(jstree, node);
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

    showNodeUpdateLoading(jstree, draggedNode);

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
    }).always(function() {
      showNodeUpdateComplete(jstree, draggedNode);
    });
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
        <CreateModal onClose={this.handleCreateModalClose}
                     isOpen={this.state.openCreateModal} />
      </div>
    );
  }
}

ReactDom.render(
  <MetricManager />,
  document.getElementById('metric-manager')
);
