import React from 'react';
import {Button, Modal, ModalHeader, ModalBody, ModalFooter} from 'reactstrap';

class MetricModal extends React.Component {
  constructor(props) {
    super(props);

    this.close = this.close.bind(this);
    this.handleSubmit = this.handleSubmit.bind(this);
    this.handleChange = this.handleChange.bind(this);

    this.submitButton = React.createRef();

    this.state = {
      metricId: null,
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
      metricId: null,
      metricName: '',
      metricDescription: '',
      metricSelectable: true,
      metricType: 'numeric',
    });
  }

  handleSubmit(event) {
    event.preventDefault();

    this.setState({submitInProgress: true});

    const data = window.jsTreeData.createMetric;
    const isRoot = data.hasOwnProperty('root') && data.root;
    const jstree = $('#jstree').jstree();
    const parentNode = isRoot ? null : jstree.get_node(data.reference);
    const parentId = isRoot ? null : parentNode.data.metricId;
    const metricName = this.state.metricName.trim();
    let submitData = {
      'context': window.metricManager.context,
      'parentId': parentId,
      'name': metricName,
      'description': this.state.metricDescription.trim(),
      'type': this.state.metricType,
      'selectable': this.state.metricSelectable,
    };
    if (this.props.mode === 'edit') {
      submitData.id = window.jsTreeData.editMetricId;
    }

    const url = this.props.mode === 'edit'
        ? '/api/metrics/edit/' + submitData.id + '.json'
        : '/api/metrics/add.json';

    $.ajax({
      method: this.props.mode === 'edit' ? 'PUT' : 'POST',
      url: url,
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
