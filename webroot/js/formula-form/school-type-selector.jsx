import React from 'react';
import PropTypes from 'prop-types';
import 'jstree';
import CheckboxContainer from './checkbox-container.jsx';

class SchoolTypeSelector extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      checkedItems: new Map(),
      onlyPublic: this.props.onlyPublic ? '1' : '0',
    };

    this.handleChangeOnlyPublic = this.handleChangeOnlyPublic.bind(this);
  }

  componentDidMount() {

  }

  handleChangeOnlyPublic(event) {
    const target = event.target;
    this.setState({onlyPublic: target.value});

    // Send results up to formula form
    let selections = [];
    if (target.value === '1') {
      selections = ['public'];
    } else {
      selections = ['todo: pull current selections from CheckboxContainer'];
    }
    const onlyPublic = target.value === '1';
    this.props.handleUpdate(onlyPublic, selections);
  }

  static capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  showAllTypes() {
    let schoolTypeCheckboxes = [];
    for (let i = 0; i < this.props.schoolTypes.length; i++) {
      const schoolType = this.props.schoolTypes[i];
      schoolTypeCheckboxes.push({
        key: 'school-type-option-' + i,
        label: SchoolTypeSelector.capitalize(schoolType.name),
        name: schoolType.name,
      });
    }

    return (
      <div id="school-type-options-breakdown">
        <CheckboxContainer checkboxes={schoolTypeCheckboxes} />
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
                 checked={this.state.onlyPublic === '1'} />
          <label className="form-check-label"
                 htmlFor="school-types-only-public">
            Public schools
          </label>
          <br />
          <input className="form-check-input" type="radio"
                 id="school-types-specified" name="onlyPublic"
                 value="0" onChange={this.handleChangeOnlyPublic}
                 checked={this.state.onlyPublic === '0'} />
          <label className="form-check-label"
                 htmlFor="school-types-specified">
            Other school types (public, private, charter, etc.)
          </label>
        </div>
        {this.state.onlyPublic === '0' &&
          this.showAllTypes()
        }
      </section>
    );
  }
}

SchoolTypeSelector.propTypes = {
  handleUpdate: PropTypes.func.isRequired,
  schoolTypes: PropTypes.array.isRequired,
  onlyPublic: PropTypes.bool.isRequired,
  schoolTypesSelected: PropTypes.array.isRequired,
};

export {SchoolTypeSelector};
