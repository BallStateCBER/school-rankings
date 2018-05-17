import React from 'react';
import {Button, Modal, ModalHeader, ModalBody, ModalFooter} from 'reactstrap';

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
            <Button color="primary" onClick={this.submit}
                    ref={this.submitButton}
                    disabled={this.state.submitInProgress}>
              Add
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

CreateModal.propTypes = Modal.propTypes;

export {CreateModal};
