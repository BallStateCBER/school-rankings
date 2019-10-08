import React from 'react';
import PropTypes from 'prop-types';
import 'jstree';
import CheckboxContainer from './checkbox-container.jsx';
import {Button} from 'reactstrap';

class SchoolTypeSelector extends React.Component {
  constructor(props) {
    super(props);
    this.handleChangeOnlyPublic = this.handleChangeOnlyPublic.bind(this);
    this.handleSelect = this.handleSelect.bind(this);
    this.handleToggleAll = this.handleToggleAll.bind(this);
  }

  handleChangeOnlyPublic(event) {
    this.props.handleChangeOnlyPublic(event.target.value === '1');
  }

  handleSelect(event) {
    // Get current set
    const schoolTypes = this.props.schoolTypes;

    // Update the relevant school type
    const schoolTypeName = event.target.name;
    const schoolType = schoolTypes.get(schoolTypeName);
    schoolType.checked = event.target.checked;
    schoolTypes.set(schoolTypeName, schoolType);

    // Update parent container
    this.props.handleSelect(schoolTypes);
  }

  handleToggleAll() {
    this.props.handleToggleAll();
  }

  render() {
    return (
      <section id="school-type col-sm-6">
        <h3>
          What types of schools?
        </h3>
        <div className="form-check">
          <input className="form-check-input" type="radio"
                 id="school-types-only-public" name="onlyPublic" value="1"
                 onChange={this.handleChangeOnlyPublic}
                 checked={this.props.onlyPublic} />
          <label className="form-check-label"
                 htmlFor="school-types-only-public">
            Public schools
          </label>
          <br />
          <input className="form-check-input" type="radio"
                 id="school-types-specified" name="onlyPublic"
                 value="0" onChange={this.handleChangeOnlyPublic}
                 checked={!this.props.onlyPublic} />
          <label className="form-check-label"
                 htmlFor="school-types-specified">
            Other school types (public, private, charter, etc.)
          </label>
        </div>
        {!this.props.onlyPublic &&
          <div id="school-type-options-breakdown" className="options-breakdown">
            <CheckboxContainer checkboxes={this.props.schoolTypes}
                               handleChange={this.handleSelect} />
            <Button color="primary" size="sm" outline={true}
                    onClick={this.handleToggleAll}>
              Toggle all
            </Button>
          </div>
        }
      </section>
    );
  }
}

SchoolTypeSelector.propTypes = {
  handleChangeOnlyPublic: PropTypes.func.isRequired,
  handleSelect: PropTypes.func.isRequired,
  handleToggleAll: PropTypes.func.isRequired,
  onlyPublic: PropTypes.bool.isRequired,
  schoolTypes: PropTypes.object.isRequired,
};

export {SchoolTypeSelector};
