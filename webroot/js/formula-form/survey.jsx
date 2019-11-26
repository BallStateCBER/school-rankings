import '../../css/formula-form.scss';
import React from 'react';
import {CustomInput} from 'reactstrap';

class Survey extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      demoChoice: null,
      demoFillIn: null,
    };
    this.handleFillInFocus = this.handleFillInFocus.bind(this);
    this.handleRadioChange = this.handleRadioChange.bind(this);
    this.handleSubmit = this.handleSubmit.bind(this);
  }

  handleRadioChange(event) {
    this.setState({demoChoice: event.target.value});
  }

  handleSubmit(event) {
    event.preventDefault();
    if (!this.validate()) {
      return;
    }

    this.setState({
      loadingRankings: true,
      progressPercent: 0,
      progressStatus: null,
      results: null,
      resultsError: false,
    });

    this.processForm();
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
        value: 'Teacher/Administrator',
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
                 onChange={this.handleRadioChange} checked={this.state.demoChoice === option.value} />
          <label className="form-check-label" htmlFor={inputName}>
            {option.label}
          </label>
        </div>
      );
      optionInputs.push(optionInput);
    });

    return optionInputs;
  }

  handleFillInFocus() {
    this.setState({demoChoice: 'Other'});
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
                   onChange={this.handleRadioChange} checked={this.state.demoChoice === 'Other'} />
            <label className="form-check-label" htmlFor="demo-choice-other">
              Other:
              <CustomInput id="demo-fill-in" type="text" inline={true} onFocus={this.handleFillInFocus} />
            </label>
          </div>
        </form>
      </div>
    );
  }
}

export {Survey};
