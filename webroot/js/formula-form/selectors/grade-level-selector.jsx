import React from 'react';
import PropTypes from 'prop-types';
import CheckboxContainer from '../checkbox-container.jsx';
import {Button} from 'reactstrap';

class GradeLevelSelector extends React.Component {
  constructor(props) {
    super(props);
    this.handleChangeAllGradeLevels = this.handleChangeAllGradeLevels.bind(this);
    this.handleSelect = this.handleSelect.bind(this);
    this.handleToggleAll = this.handleToggleAll.bind(this);
  }

  handleChangeAllGradeLevels(event) {
    this.props.handleChangeAllGradeLevels(event.target.value === '1');
  }

  handleSelect(event) {
    // Get current set
    const gradeLevels = this.props.gradeLevels;

    // Update the relevant grade level
    const gradeLevelId = event.target.name;
    const gradeLevel = gradeLevels.get(gradeLevelId);
    gradeLevel.checked = event.target.checked;
    gradeLevels.set(gradeLevelId, gradeLevel);

    // Update parent container
    this.props.handleSelect(gradeLevels);
  }

  handleToggleAll() {
    this.props.handleToggleAll();
  }

  render() {
    return (
      <div>
        <div className="form-check">
          <input className="form-check-input" type="radio"
                 id="grade-levels-any" name="allGradeLevels" value="1"
                 onChange={this.handleChangeAllGradeLevels}
                 checked={this.props.allGradeLevels} />
          <label className="form-check-label"
                 htmlFor="grade-levels-any">
            Any (preschool through 12th grade)
          </label>
          <br />
          <input className="form-check-input" type="radio"
                 id="grade-levels-specified" name="allGradeLevels"
                 value="0" onChange={this.handleChangeAllGradeLevels}
                 checked={!this.props.allGradeLevels} />
          <label className="form-check-label"
                 htmlFor="grade-levels-specified">
            Specific grade levels
          </label>
        </div>
        {!this.props.allGradeLevels &&
          <div id="grade-levels-options-breakdown" className="options-breakdown">
            <CheckboxContainer checkboxes={this.props.gradeLevels}
                               handleChange={this.handleSelect} />
            <Button color="primary" size="sm" outline={true}
                    onClick={this.handleToggleAll}>
              Toggle all
            </Button>
          </div>
        }
      </div>
    );
  }
}

GradeLevelSelector.propTypes = {
  allGradeLevels: PropTypes.bool.isRequired,
  gradeLevels: PropTypes.object.isRequired,
  handleChangeAllGradeLevels: PropTypes.func.isRequired,
  handleSelect: PropTypes.func.isRequired,
  handleToggleAll: PropTypes.func.isRequired,
};

export {GradeLevelSelector};
