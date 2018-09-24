import React from 'react';
import PropTypes from 'prop-types';
import 'jstree';
import CheckboxContainer from './checkbox-container.jsx';

class SchoolTypeSelector extends React.Component {
  constructor(props) {
    super(props);
    this.handleChangeOnlyPublic = this.handleChangeOnlyPublic.bind(this);
    this.handleSelectSchoolTypes = this.handleSelectSchoolTypes.bind(this);
  }

  handleChangeOnlyPublic(event) {
    this.props.handleChangeOnlyPublic(event.target.value === '1');
  }

  handleSelectSchoolTypes(event) {
    // Get current set
    let schoolTypes = this.props.schoolTypes;

    // Update the relevant school type
    const schoolTypeName = event.target.name;
    let schoolType = schoolTypes.get(schoolTypeName);
    schoolType.checked = event.target.checked;
    schoolTypes.set(schoolTypeName, schoolType);

    // Update parent container
    this.props.handleSelectSchoolTypes(schoolTypes);
  }

  showAllTypes() {
    return (
      <div id="school-type-options-breakdown">
        <CheckboxContainer checkboxes={this.props.schoolTypes}
                           handleChange={this.handleSelectSchoolTypes} />
      </div>
    );
  }

  render() {
    return (
      <section id="school-type">
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
          this.showAllTypes()
        }
      </section>
    );
  }
}

SchoolTypeSelector.propTypes = {
  handleChangeOnlyPublic: PropTypes.func.isRequired,
  handleSelectSchoolTypes: PropTypes.func.isRequired,
  onlyPublic: PropTypes.bool.isRequired,
  schoolTypes: PropTypes.object.isRequired,
};

export {SchoolTypeSelector};
