import React from 'react';
import ReactDom from 'react-dom';
import {Button} from 'reactstrap';
import uuidv4 from 'uuid/v4';
import Select from 'react-select';
import '../../../node_modules/react-select/dist/react-select.css';
import {MetricSelector} from './metric-selector.jsx';

class FormulaForm extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      context: null,
      county: 'bar',
      uuid: FormulaForm.getUuid(),
    };
    FormulaForm.handleSubmit = FormulaForm.handleSubmit.bind(this);
    this.handleChange = this.handleChange.bind(this);
    this.handleSelectCounty = this.handleSelectCounty.bind(this);
  }

  static getUuid() {
    return uuidv4();
  }

  handleChange(event) {
    const target = event.target;
    const name = target.name;
    const value = target.type === 'checkbox' ? target.checked : target.value;

    this.setState({[name]: value});
  }

  handleSelectCounty(selectedOption) {
    this.setState({
      county: selectedOption ? selectedOption.value : null,
    });
  }

  static handleSubmit(event) {
    event.preventDefault();
  }

  static getCountyOptions() {
    let selectOptions = [];
    for (let n = 0; n < window.formulaForm.counties.length; n++) {
      let county = window.formulaForm.counties[n];
      selectOptions.push({
        value: county.id,
        label: county.name,
      });
    }

    return selectOptions;
  }

  render() {
    return (
      <form onSubmit={FormulaForm.handleSubmit}>
        <div className="form-group">
          <label>
            What would you like to rank?
          </label>
          <div className="form-check">
            <input className="form-check-input" type="radio" name="context"
                   id="context-school" value="school"
                   onChange={this.handleChange}
                   checked={this.state.context === 'school'} />
            <label className="form-check-label" htmlFor="context-school">
              Schools
            </label>
          </div>
          <div className="form-check">
            <input className="form-check-input" type="radio" name="context"
                   id="context-district" value="district"
                   onChange={this.handleChange}
                   checked={this.state.context === 'district'} />
            <label className="form-check-label" htmlFor="context-district">
              School corporations (districts)
            </label>
          </div>
        </div>
        <div className="form-group">
          <label htmlFor="county">
            County
          </label>
          <Select name="county" id="county"
                  value={this.state.county} onChange={this.handleSelectCounty}
                  options={FormulaForm.getCountyOptions()} clearable={false} />
        </div>
        {this.state.context === 'school' &&
          <MetricSelector context="school" />
        }
        {this.state.context === 'district' &&
          <MetricSelector context="district" />
        }
        <Button color="primary" onClick={FormulaForm.handleSubmit}
                ref={this.submitButton}
                disabled={this.state.submitInProgress}>
          Submit
        </Button>
      </form>
    );
  }
}

export {FormulaForm};

ReactDom.render(
    <FormulaForm/>,
    document.getElementById('formula-form')
);
