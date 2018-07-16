import React from 'react';
import ReactDom from 'react-dom';
import {Button} from 'reactstrap';
import uuidv4 from 'uuid/v4';
import Select from 'react-select';
import '../../../node_modules/react-select/dist/react-select.css';
import {MetricSelector} from './metric-selector.jsx';
import '../../css/formula-form.scss';
import {Criterion} from './criterion.jsx';

class FormulaForm extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      context: null,
      county: null,
      criteria: [],
      uuid: FormulaForm.getUuid(),
      passesValidation: false,
    };
    this.handleChange = this.handleChange.bind(this);
    this.handleSelectCounty = this.handleSelectCounty.bind(this);
    this.handleSubmit = this.handleSubmit.bind(this);
    this.handleClearMetrics = this.handleClearMetrics.bind(this);
    this.handleSelectMetric = this.handleSelectMetric.bind(this);
    this.handleUnselectMetric = this.handleUnselectMetric.bind(this);
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

  handleSubmit(event) {
    event.preventDefault();
    this.validate();
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

  validate() {
    const context = this.state.context;
    const county = this.state.county;
    const criteria = this.state.criteria;
    if (!context) {
      alert('Please select either schools or school corporations (districts)');
      this.setState({passesValidation: false});
      return;
    }
    if (!county) {
      alert('Please select a county');
      this.setState({passesValidation: false});
      return;
    }
    if (!criteria.length) {
      alert('Please select one or more metrics');
      this.setState({passesValidation: false});
      return;
    }

    this.setState({passesValidation: true});
  }

  handleSelectMetric(node, selected) {
    // Ignore non-selectable metrics
    if (!selected.node.data.selectable) {
      return;
    }

    let metric = {
      metricId: selected.node.data.metricId,
      dataType: selected.node.data.type,
      name: selected.node.data.name,
    };

    // Add parents to metric name
    for (let i = 0; i < selected.node.parents.length; i++) {
      const parentId = selected.node.parents[i];
      if (parentId === '#') {
        continue;
      }
      const jstree = $('#jstree').jstree(true);
      const node = jstree.get_node(parentId);
      metric.name = node.text + ' > ' + metric.name;
    }

    // Add criterion
    const criterion = {
      metric: metric,
    };
    let criteria = this.state.criteria;
    criteria.push(criterion);
    this.setState({criteria: criteria});
  }

  handleUnselectMetric(node, selected) {
    let criteria = this.state.criteria;
    const unselectedMetricId = selected.node.data.metricId;
    const filteredCriteria = criteria.filter(
        (criterion) => criterion.metric.metricId !== unselectedMetricId
    );
    this.setState({criteria: filteredCriteria});
  }

  handleClearMetrics() {
    this.setState({criteria: []});
    $('#jstree').jstree(true).deselect_all();
  }

  render() {
    return (
      <form onSubmit={this.handleSubmit}>
        <div className="form-group">
          <h3>
            <label>
              What would you like to rank?
            </label>
          </h3>
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
        <h3>
          Where would you like to search?
        </h3>
        <div className="form-group">
          <label htmlFor="county">
            County
          </label>
          <Select name="county" id="county"
                  value={this.state.county} onChange={this.handleSelectCounty}
                  options={FormulaForm.getCountyOptions()} clearable={false}
                  required={true} />
        </div>
        {this.state.context &&
          <MetricSelector context={this.state.context}
                          handleSelectMetric={this.handleSelectMetric}
                          handleUnselectMetric={this.handleUnselectMetric}
                          handleClearMetrics={this.handleClearMetrics} />
        }
        {this.state.criteria.length > 0 &&
          <div id="criteria">
            {this.state.criteria.map((criterion) => {
              return (
                <Criterion key={criterion.metric.metricId}
                           name={criterion.metric.name}
                           metricId={criterion.metric.metricId}>
                </Criterion>
              );
            })}
          </div>
        }
        <Button color="primary" onClick={this.handleSubmit}
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
