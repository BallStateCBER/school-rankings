import '../../css/formula-form.scss';
import PropTypes from 'prop-types';
import React from 'react';
import {CustomInput} from 'reactstrap';

class Survey extends React.Component {
  constructor(props) {
    super(props);

    this.handleFillInChange = this.handleFillInChange.bind(this);
    this.handleFillInFocus = this.handleFillInFocus.bind(this);
    this.handleRadioChange = this.handleRadioChange.bind(this);
  }

  handleRadioChange(event) {
    this.props.handleRadioChange(event.target.value);
  }

  handleFillInFocus() {
    this.props.handleFillInFocus();
  }

  handleFillInChange(event) {
    this.props.handleFillInChange(event.target.value);
  }

  getOptions() {
    return [
      {
        value: 'Parent',
        label: <span>A <strong>parent</strong> of a student or prospective student</span>,
        key: 'parent',
      },
      {
        value: 'Student',
        label: <span>A <strong>student</strong> in elementary, middle or high school</span>,
        key: 'student',
      },
      {
        value: 'Teacher or Administrator',
        label: <span>A <strong>teacher</strong> or <strong>school administrator</strong></span>,
        key: 'teacher',
      },
    ];
  }

  getOptionInputs() {
    const optionInputs = [];
    this.getOptions().forEach((option) => {
      const inputName = 'demo-choice-' + option.key;
      const optionInput = (
        <div className="form-check" key={option.key}>
          <input className="form-check-input" type="radio" id={inputName} value={option.value}
                 onChange={this.handleRadioChange} checked={this.props.choice === option.value} />
          <label className="form-check-label" htmlFor={inputName}>
            {option.label}
          </label>
        </div>
      );
      optionInputs.push(optionInput);
    });

    return optionInputs;
  }

  render() {
    return (
      <div id="survey">
        <h3>
          Which of the following best describes you? <span className="text-muted">(optional)</span>
        </h3>
        <form>
          {this.getOptionInputs()}
          <div className="form-check" key="other">
            <input className="form-check-input" type="radio" id="demo-choice-other" value="Other"
                   onChange={this.handleRadioChange} checked={this.props.choice === 'Other'} />
            <label className="form-check-label" htmlFor="demo-choice-other">
              Other:
              <CustomInput id="demo-fill-in" type="text" inline={true} onFocus={this.handleFillInFocus}
                           onChange={this.handleFillInChange} value={this.props.fillIn} />
            </label>
          </div>
        </form>
      </div>
    );
  }
}

Survey.propTypes = {
  choice: PropTypes.string,
  fillIn: PropTypes.string,
  handleFillInChange: PropTypes.func.isRequired,
  handleFillInFocus: PropTypes.func.isRequired,
  handleRadioChange: PropTypes.func.isRequired,
};

export {Survey};
