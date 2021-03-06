import React from 'react';
import {Button, Modal, ModalHeader, ModalBody, ModalFooter} from 'reactstrap';
import {MetricManager} from './metric-manager.jsx';

class MetricModal extends React.Component {
  constructor(props) {
    super(props);

    this.close = this.close.bind(this);
    this.handleSubmit = this.handleSubmit.bind(this);
    this.handleChange = this.handleChange.bind(this);
    this.resetModalState = this.resetModalState.bind(this);

    this.submitButton = React.createRef();

    this.state = {
      metricId: null,
      metricName: '',
      metricDescription: '',
      metricSelectable: true,
      metricVisible: true,
      metricType: 'numeric',
      submitInProgress: false,
    };

    // If in edit mode, populate fields
    if (this.props.mode === 'edit') {
      if (! window.jsTreeData.editMetric.hasOwnProperty('metricId')) {
        return;
      }

      const data = window.jsTreeData.editMetric;
      if (this.state.metricId === data.metricId) {
        return;
      }

      this.state.metricId = data.metricId;
      this.state.metricName = data.name;
      this.state.metricDescription = data.description;
      this.state.metricSelectable = data.selectable;
      this.state.metricVisible = data.visible;
      this.state.metricType = data.type;
    }
  }

  handleChange(event) {
    const target = event.target;
    const name = target.name;
    const value = target.type === 'checkbox' ? target.checked : target.value;

    this.setState({[name]: value});
  }

  close() {
    this.resetModalState();
    this.props.onClose();
  }

  handleAdd() {
    const data = window.jsTreeData.createMetric;
    const isRoot = data.hasOwnProperty('root') && data.root;
    const jstree = $('#jstree').jstree(true);
    const parentNode = isRoot ? null : jstree.get_node(data.reference);
    const parentId = isRoot ? null : parentNode.data.metricId;
    const metricName = this.state.metricName.trim();
    const submitData = {
      context: window.metricManager.context,
      parentId: parentId,
      name: metricName,
      description: this.state.metricDescription.trim(),
      type: this.state.metricType,
      selectable: this.state.metricSelectable,
      visible: this.state.metricVisible,
    };

    $.ajax({
      method: 'POST',
      url: '/api/metrics/add.json',
      dataType: 'json',
      data: submitData,
    }).done((responseData) => {
      const nodeData = MetricModal.getNewNodeData(submitData, responseData);
      jstree.create_node(parentNode, nodeData);
      this.close();
    }).fail((jqXHR) => {
      const msg = jqXHR.responseJSON.message;
      alert(msg);
      this.setState({submitInProgress: false});
    });
  }

  static getNewNodeData(submitData, responseData) {
    return {
      text: submitData.name,
      li_attr: {
        'data-selectable': submitData.selectable,
        'data-type': submitData.type,
        'data-visible': submitData.visible ? 1 : 0,
      },
      icon: submitData.selectable ? 'far fa-check-circle' : 'fas fa-ban',
      data: {
        description: submitData.description,
        metricId: responseData.metricId,
        name: submitData.name,
        selectable: submitData.selectable,
        type: submitData.type,
        visible: submitData.visible,
      },
    };
  }

  handleEdit() {
    const submitData = {
      context: window.metricManager.context,
      name: this.state.metricName.trim(),
      description: this.state.metricDescription.trim(),
      type: this.state.metricType,
      selectable: this.state.metricSelectable,
      visible: this.state.metricVisible,
    };

    $.ajax({
      method: 'PUT',
      url: '/api/metrics/edit/' + this.state.metricId + '.json',
      dataType: 'json',
      data: submitData,
    }).done(() => {
      const node = window.jsTreeData.editMetricNode;
      MetricManager.updateNode(node, submitData);
      this.close();
    }).fail((jqXHR) => {
      if (jqXHR.hasOwnProperty('responseJSON')) {
        if (jqXHR.responseJSON.hasOwnProperty('message')) {
          alert(jqXHR.responseJSON.message);
        }
      }
      alert('Error updating metric.');
      this.setState({submitInProgress: false});
    });
  }

  handleSubmit(event) {
    event.preventDefault();
    this.setState({submitInProgress: true});
    if (this.props.mode === 'add') {
      this.handleAdd();
    } else if (this.props.mode === 'edit') {
      this.handleEdit();
    }
  }

  resetModalState() {
      this.setState({
          metricId: null,
          metricName: '',
          metricDescription: '',
          metricSelectable: true,
          metricVisible: true,
          metricType: 'numeric',
          submitInProgress: false,
      });
  }

  render() {
    return (
      <Modal isOpen={this.props.isOpen} toggle={this.close}
             className={this.props.className}
             ref={(modal) => this.modal = modal}>
        <ModalHeader toggle={this.close}>
          {this.props.mode === 'add' ? 'Create New ' : 'Edit '}
          Metric
        </ModalHeader>
        <form onSubmit={this.handleSubmit}>
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
                <label className="form-check-label" htmlFor="numeric-radio">
                  Numeric
                </label>
              </div>
              <div className="form-check">
                <input className="form-check-input" type="radio"
                       name="metricType" id="boolean-radio" value="boolean"
                       checked={this.state.metricType === 'boolean'}
                       onChange={this.handleChange} />
                <label className="form-check-label" htmlFor="boolean-radio">
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
            <fieldset className="form-group">
              <div className="form-check">
                <input className="form-check-input" type="checkbox"
                       value="1" id="visible-checkbox"
                       name="metricVisible"
                       checked={this.state.metricVisible}
                       onChange={this.handleChange} />
                <label className="form-check-label"
                       htmlFor="visible-checkbox">
                  Visible
                </label>
              </div>
            </fieldset>
          </ModalBody>
          <ModalFooter>
            <Button color="primary" onClick={this.handleSubmit}
                    ref={this.submitButton}
                    disabled={this.state.submitInProgress}>
              {this.props.mode === 'add' ? 'Add' : 'Edit'}
            </Button>
            {' '}
            <Button color="secondary" onClick={this.close} data-dismiss="modal">
              Cancel
            </Button>
          </ModalFooter>
        </form>
      </Modal>
    );
  }
}

MetricModal.propTypes = Modal.propTypes;

export {MetricModal};
