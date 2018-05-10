import React from 'react';
import PropTypes from 'prop-types';
import 'jstree';
import '../css/metric-manager.scss';
import ReactDom from 'react-dom';
import 'bootstrap';
import {Button, Modal, ModalHeader, ModalBody, ModalFooter} from 'reactstrap';

window.jsTreeData = [];

class PrevModalHeader extends React.Component {
  render() {
    return (
        <div className="modal-header">
          <h5 className="modal-title">{this.props.title}</h5>
          <button type="button" className="close" data-dismiss="modal"
                  aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
    );
  }
}

PrevModalHeader.propTypes = {
  title: PropTypes.string.isRequired,
};

class CreateModal extends React.Component {
  componentDidMount() {

  }

  render() {
    let modal = $('#modal');
    modal.modal('show');
    modal.find('form').submit((event) => {
      handleCreateSubmit(event, jsTreeData);
    });


    return (
        <div className="modal" tabIndex="-1" role="dialog">
          <div className="modal-dialog" role="document">
            <div className="modal-content">
              <form onSubmit={handleCreateSubmit}>
                <ModalHeader title="Add metric" />
                <div className="modal-body">
                  <fieldset className="form-group">
                    <label htmlFor="metric-name">Name:</label>
                    <input id="metric-name" type="text" className="form-control"
                           required="required"/>
                  </fieldset>
                  <fieldset className="form-group">
                    <label htmlFor="metric-description">Description:</label>
                    <textarea id="metric-description" className="form-control"
                              rows="3"></textarea>
                  </fieldset>
                  <fieldset className="form-group">
                    <div className="form-check">
                      <input className="form-check-input" type="radio"
                             name="type" id="numeric-radio" value="numeric"
                             defaultChecked="checked" />
                      <label className="form-check-label"
                             htmlFor="numeric-radio">
                        Numeric
                      </label>
                    </div>
                    <div className="form-check">
                      <input className="form-check-input" type="radio"
                             name="type" id="boolean-radio" value="boolean" />
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
                             defaultChecked="checked"/>
                      <label className="form-check-label"
                             htmlFor="selectable-checkbox">
                        Selectable
                      </label>
                    </div>
                  </fieldset>
                </div>
                <div className="modal-footer">
                  <button type="submit" className="btn btn-primary">Add</button>
                  <button type="button" className="btn btn-secondary"
                          data-dismiss="modal">Cancel
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
    );
  }
}

class CreateModalReactstrap extends React.Component {
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
  }

  submit(event) {
    event.preventDefault();

    this.setState({submitInProgress: true});

    const data = window.jsTreeData.createMetric;
    let jstree = $.jstree.reference(data.reference);
    let parentNode = jstree.get_node(data.reference);
    const context = window.metricManager.context;
    let metricId = parentNode.data.metricId;

    const metricName = this.state.metricName.trim();
    const description = this.state.metricDescription.trim();
    const type = this.state.metricType;
    const selectable = this.state.metricSelectable;

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
    }).done(() => {
      jstree.create_node(parentNode, metricName);

      // modal.modal('hide').data('bs.modal', null);
      this.close();
    }).fail((jqXHR) => {
      const msg = jqXHR.responseJSON.message;
      alert(msg);
      this.setState({submitInProgress: false});
    });
  }

  render() {
    return (
      <div>
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
      </div>
    );
  }
}

CreateModalReactstrap.propTypes = Modal.propTypes;

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

let renameConfig = {
  'label': 'Rename',
  'title': 'Rename this metric',
  'action': function(data) {
    let jstree = $.jstree.reference(data.reference);
    let node = jstree.get_node(data.reference);
    const context = window.metricManager.context;
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

      beforeSend(liElement, jstree, node);

      $.ajax({
        method: 'PATCH',
        url: '/admin/metrics/rename.json',
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
        onComplete(liElement, jstree, node);
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
    const context = window.metricManager.context;
    let metricId = node.data.metricId;

    if (jstree.is_parent(node)) {
      alert('Cannot delete a metric that has children');
      return;
    }

    beforeSend(liElement, jstree, node);

    $.ajax({
      method: 'DELETE',
      url: '/admin/metrics/delete.json',
      dataType: 'json',
      data: {
        'context': context,
        'metricId': metricId,
      },
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
      onComplete(liElement, jstree, node);
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

  handleCreateModalOpen() {
    this.setState({openCreateModal: true});
  }

  handleCreateModalClose() {
    this.setState({openCreateModal: false});
  }

  componentDidMount() {
    this.setState({loading: true});
    const context = window.metricManager.context;

    $.ajax({
      method: 'GET',
      url: '/api/metrics/' + context + 's.json',
      dataType: 'json',
    }).done((data) => {
      $('#jstree').jstree({
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
              'Rename': renameConfig,
              'Delete': deleteConfig,
            };
          },
        },
      });
    }).fail((jqXHR) => {
      this.setState({
        hasError: true,
        errorMsg: jqXHR.responseJSON.message,
      });
    }).always(() => {
      this.setState({loading: false});
    });
  }

  render() {
    const isLoading = this.state.loading;
    const hasError = this.state.hasError;
    const errorMsg = this.state.errorMsg;

    return <div>
      {isLoading &&
        <span className="loading">
          Loading data...
          <img src="/jstree/themes/default/throbber.gif" alt="Loading..."
               className="loading"/>
        </span>
      }
      {hasError &&
        <p className="text-danger">{errorMsg}</p>
      }
      <div id="jstree"></div>
      <CreateModalReactstrap onClose={this.handleCreateModalClose}
                             isOpen={this.state.openCreateModal} />
    </div>;
  }
}

ReactDom.render(
  <MetricManager />,
  document.getElementById('metric-manager')
);
