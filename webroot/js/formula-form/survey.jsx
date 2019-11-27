import '../../css/formula-form.scss';
import React from 'react';
import {CustomInput} from 'reactstrap';
import Cookies from 'universal-cookie';

class Survey extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      demoChoice: null,
      demoFillIn: '',
    };
    this.handleFillInFocus = this.handleFillInFocus.bind(this);
    this.handleRadioChange = this.handleRadioChange.bind(this);
    this.handleFillInChange = this.handleFillInChange.bind(this);
    this.cookies = new Cookies();
  }

  /**
   * Automatically fills out the survey form using Cookie data
   */
  componentDidMount() {
    const savedDemoChoice = this.cookies.get('demoChoice');
    const savedDemoFillIn = this.cookies.get('demoFillIn');
    if (!savedDemoChoice) {
      return;
    }
    this.setState({demoChoice: savedDemoChoice});

    if (savedDemoChoice !== 'Other') {
      return;
    }
    this.setState({demoFillIn: savedDemoFillIn});
  }

  handleRadioChange(event) {
    this.setState({demoChoice: event.target.value});
    this.cookies.set('demoChoice', event.target.value);
  }

  handleFillInChange(event) {
    this.setState({demoFillIn: event.target.value});
    this.cookies.set('demoFillIn', event.target.value);
  }

  handleFillInFocus() {
    this.setState({demoChoice: 'Other'});
    this.cookies.set('demoChoice', 'Other');
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
              <CustomInput id="demo-fill-in" type="text" inline={true} onFocus={this.handleFillInFocus}
                           onChange={this.handleFillInChange} value={this.state.demoFillIn} />
            </label>
          </div>
        </form>
      </div>
    );
  }
}

export {Survey};
