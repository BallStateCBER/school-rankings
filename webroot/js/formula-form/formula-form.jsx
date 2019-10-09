import '../../css/formula-form.scss';
import React from 'react';
import ReactDom from 'react-dom';
import Select from 'react-select';
import uuidv4 from 'uuid/v4';
import {Button} from 'reactstrap';
import {ContextSelector} from './selectors/context-selector.jsx';
import {Criterion} from './criterion.jsx';
import {GradeLevelSelector} from './selectors/grade-level-selector.jsx';
import {MetricSelector} from './selectors/metric-selector.jsx';
import {NoDataResults} from './results/no-data-results.jsx';
import {ProgressBar} from './progress-bar.jsx';
import {RankingResults} from './results/ranking-results.jsx';
import {SchoolTypeSelector} from './selectors/school-type-selector.jsx';

class FormulaForm extends React.Component {
  constructor(props) {
    super(props);
    this.formulaId = null;
    this.rankingId = null;
    this.jobId = null;
    this.state = {
      allGradeLevels: true,
      context: null,
      county: null,
      criteria: [],
      gradeLevels: new Map(),
      loadingRankings: false,
      noDataResults: null,
      onlyPublic: true,
      passesValidation: false,
      progressPercent: null,
      progressStatus: null,
      results: null,
      schoolTypes: new Map(),
      uuid: FormulaForm.getUuid(),
    };
    this.handleChange = this.handleChange.bind(this);
    this.handleChangeAllGradeLevels = this.handleChangeAllGradeLevels.bind(this);
    this.handleChangeContext = this.handleChangeContext.bind(this);
    this.handleChangeOnlyPublic = this.handleChangeOnlyPublic.bind(this);
    this.handleClearMetrics = this.handleClearMetrics.bind(this);
    this.handleRemoveCriterion = this.handleRemoveCriterion.bind(this);
    this.handleSelectCounty = this.handleSelectCounty.bind(this);
    this.handleSelectGradeLevels = this.handleSelectGradeLevels.bind(this);
    this.handleSelectMetric = this.handleSelectMetric.bind(this);
    this.handleSelectSchoolTypes = this.handleSelectSchoolTypes.bind(this);
    this.handleSubmit = this.handleSubmit.bind(this);
    this.handleToggleAllGradeLevels = this.handleToggleAllGradeLevels.bind(this);
    this.handleToggleAllSchoolTypes = this.handleToggleAllSchoolTypes.bind(this);
    this.handleUnselectMetric = this.handleUnselectMetric.bind(this);
  }

  componentDidMount() {
    this.setSchoolTypes();
    this.setGradeLevels();
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
      county: selectedOption ? selectedOption : null,
    });
  }

  handleChangeOnlyPublic(onlyPublic) {
    this.setState({onlyPublic: onlyPublic});
  }

  handleSelectSchoolTypes(schoolTypes) {
    this.setState({schoolTypes: schoolTypes});
  }

  handleToggleAllSchoolTypes() {
    this.toggleCollection('schoolTypes');
  }

  handleChangeAllGradeLevels(allGradeLevels) {
    this.setState({allGradeLevels: allGradeLevels});
  }

  handleSelectGradeLevels(gradeLevels) {
    this.setState({gradeLevels: gradeLevels});
  }

  handleToggleAllGradeLevels() {
    this.toggleCollection('gradeLevels');
  }

  /**
   * Takes the name of a Map of checkbox objects in this.state and sets all values to be the opposite of the current
   * majority of values
   *
   * @param {string} key
   */
  toggleCollection(key) {
    const items = this.state[key];

    // Set all checkboxes to the opposite of the current majority state
    let checkedCount = 0;
    let uncheckedCount = 0;
    const itemsArray = Array.from(items.values());
    for (let i = 0; i < itemsArray.length; i++) {
      const item = itemsArray[i];
      if (item.checked) {
        checkedCount++;
      } else {
        uncheckedCount++;
      }
    }
    const newCheckedState = checkedCount < uncheckedCount;

    items.forEach(function(item) {
      item.checked = newCheckedState;
    });

    const stateObject = {};
    stateObject[key] = items;
    this.setState(stateObject);
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
    });

    this.processForm();
  }

  processForm() {
    return $.ajax({
      method: 'POST',
      url: '/api/formulas/add/',
      dataType: 'json',
      data: {
        context: this.state.context,
        criteria: this.state.criteria,
      },
    }).done((data) => {
      if (
        !data.hasOwnProperty('success') ||
        !data.hasOwnProperty('id') ||
        !data.success
      ) {
        console.log('Error creating formula record');
        console.log(data);
        this.setState({loadingRankings: false});
        return;
      }

      this.setState({
        progressPercent: 10,
        progressStatus: 'Preparing calculation',
      });

      this.formulaId = data.id;
      this.startRankingJob();
    }).fail((jqXHR) => {
      FormulaForm.logApiError(jqXHR);
    });
  }

  startRankingJob() {
    if (!this.formulaId) {
      console.log('Error: Formula ID not found');
      this.setState({loadingRankings: false});
      return;
    }

    const data = {
      countyId: this.state.county.value,
      formulaId: this.formulaId,
      schoolTypes: this.getSelectedSchoolTypes(),
      gradeLevels: this.getSelectedGradeLevels(),
    };

    return $.ajax({
      method: 'POST',
      url: '/api/rankings/add/',
      dataType: 'json',
      data: data,
    }).done((data) => {
      if (FormulaForm.hasErrorCreatingJob(data)) {
        console.log('Error creating ranking record');
        console.log(data);
        return;
      }

      this.setState({
        progressPercent: 20,
        progressStatus: 'Preparing calculation',
      });

      this.rankingId = data.rankingId;
      this.jobId = data.jobId;
      this.checkJobProgress(this.jobId);
    }).fail((jqXHR) => {
      FormulaForm.logApiError(jqXHR);
    });
  }

  static hasErrorCreatingJob(data) {
    return !data.hasOwnProperty('success') ||
      !data.hasOwnProperty('rankingId') ||
      !data.hasOwnProperty('jobId') ||
      !data.success ||
      !data.rankingId ||
      !data.jobId;
  }

  static logApiError(jqXHR) {
    let errorMsg = 'Error loading rankings';
    if (jqXHR.hasOwnProperty('responseJSON')) {
      if (jqXHR.responseJSON.hasOwnProperty('message')) {
        errorMsg = jqXHR.responseJSON.message;
      }
    }
    console.log('Error: ' + errorMsg);
  }

  static getCountyOptions() {
    const selectOptions = [];
    for (let n = 0; n < window.formulaForm.counties.length; n++) {
      const county = window.formulaForm.counties[n];
      selectOptions.push({
        value: county.id,
        label: county.name,
      });
    }

    return selectOptions;
  }

  static capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  /**
   * Read school type data from window.formulaForm and load it into state
   */
  setSchoolTypes() {
    const schoolTypes = new Map();
    for (let n = 0; n < window.formulaForm.schoolTypes.length; n++) {
      const schoolTypeData = window.formulaForm.schoolTypes[n];
      const schoolType = {
        checked: schoolTypeData.name === 'public',
        id: schoolTypeData.id,
        name: schoolTypeData.name,
        key: 'school-type-option-' + n,
        label: FormulaForm.capitalize(schoolTypeData.name),
      };
      schoolTypes.set(schoolType.name, schoolType);
    }
    this.setState({schoolTypes: schoolTypes});
  }

  /**
   * Read grade level data from window.formulaForm and load it into state
   */
  setGradeLevels() {
    const gradeLevels = new Map();
    for (let n = 0; n < window.formulaForm.gradeLevels.length; n++) {
      const gradeLevelData = window.formulaForm.gradeLevels[n];
      const gradeLevel = {
        checked: false,
        id: gradeLevelData.id,
        name: gradeLevelData.slug,
        key: 'grade-level-option-' + n,
        label: gradeLevelData.name,
      };
      gradeLevels.set(gradeLevel.name, gradeLevel);
    }
    this.setState({gradeLevels: gradeLevels});
  }

  validate() {
    const context = this.state.context;
    const county = this.state.county;
    const criteria = this.state.criteria;
    if (!context) {
      alert('Please select either schools or school corporations (districts)');
      return false;
    }
    if (!county) {
      alert('Please select a county');
      return false;
    }
    if (!criteria.length) {
      alert('Please select one or more metrics');
      return false;
    }
    if (context === 'school') {
      if (!this.state.allGradeLevels && this.getSelectedGradeLevels().length === 0) {
        alert('Please specify at least one grade level');
        return false;
      }
      if (this.getSelectedSchoolTypes().length === 0) {
        alert('Please specify at least one type of school');
        return false;
      }
    }

    return true;
  }

  handleSelectMetric(node, selected) {
    // Ignore non-selectable metrics
    if (!selected.node.data.selectable) {
      return;
    }

    const metric = {
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
    const criteria = this.state.criteria;
    criteria.push(criterion);
    this.setState({criteria: criteria});
  }

  handleUnselectMetric(node, selected) {
    const criteria = this.state.criteria;
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

  checkJobProgress(jobId) {
    $.ajax({
      method: 'GET',
      url: '/api/rankings/status/',
      dataType: 'json',
      data: {
        jobId: jobId,
      },
    }).done((data) => {
      if (
        !data.hasOwnProperty('progress') ||
        !data.hasOwnProperty('status')
      ) {
        console.log('Error checking job status');
        console.log(data);
        this.setState({loadingRankings: false});
        return;
      }

      this.setState({
        progressPercent: 20 + (data.progress * 80),
        progressStatus: data.status ?
          data.status :
          'Starting calculation engine',
      });

      // Not finished yet
      if (data.progress !== 1) {
        setTimeout(
          () => {
            this.checkJobProgress(jobId);
          },
          500
        );
        return;
      }

      // Job complete
      this.loadResults();
    }).fail((jqXHR) => {
      FormulaForm.logApiError(jqXHR);
    });
  }

  loadResults() {
    $.ajax({
      method: 'GET',
      url: '/api/rankings/get/' + this.rankingId + '.json',
      dataType: 'json',
    }).done((data) => {
      if (!data.hasOwnProperty('results')) {
        console.log('Error retrieving ranking results');
        console.log(data);
        return;
      }

      this.setState({
        results: data.results,
        noDataResults: data.noDataResults,
      });
    }).fail((jqXHR) => {
      FormulaForm.logApiError(jqXHR);
    }).always(() => {
      this.setState({loadingRankings: false});
    });
  }

  handleRemoveCriterion(metricId) {
    const filteredCriteria = this.state.criteria.filter((i) => i.metric.metricId !== metricId);
    this.setState({criteria: filteredCriteria});
    const jstree = $('#jstree').jstree(true);
    jstree.deselect_node('li[data-metric-id=' + metricId + ']');
  }

  getSelectedSchoolTypes() {
    if (this.state.context !== 'school') {
      return [];
    }

    if (this.state.onlyPublic) {
      return ['public'];
    }

    const selectedSchoolTypes = [];
    this.state.schoolTypes.forEach(function(schoolType) {
      if (schoolType.checked) {
        selectedSchoolTypes.push(schoolType.name);
      }
    });
    return selectedSchoolTypes;
  }

  getSelectedGradeLevels() {
    if (this.state.context !== 'school' || this.state.allGradeLevels) {
      return [];
    }

    const selectedGradeLevels = [];
    this.state.gradeLevels.forEach(function(gradeLevel) {
      if (gradeLevel.checked) {
        selectedGradeLevels.push(gradeLevel.name);
      }
    });
    return selectedGradeLevels;
  }

  handleChangeContext(event) {
    this.setState({context: event.target.value});
    this.setState({criteria: []});
  }

  render() {
    return (
      <div>
        <form onSubmit={this.handleSubmit}>
          <section>
            <h3>
              Where would you like to search?
            </h3>
            <div className="form-group">
              <label htmlFor="county">
                County
              </label>
              <Select name="county" id="county"
                      value={this.state.county}
                      onChange={this.handleSelectCounty}
                      options={FormulaForm.getCountyOptions()} clearable={false}
                      required={true} />
            </div>
          </section>
          <div className="row">
            <ContextSelector context={this.state.context}
                             handleChange={this.handleChangeContext} />
            {this.state.context === 'school' &&
              <SchoolTypeSelector schoolTypes={this.state.schoolTypes}
                                  onlyPublic={this.state.onlyPublic}
                                  handleSelect={this.handleSelectSchoolTypes}
                                  handleChangeOnlyPublic={this.handleChangeOnlyPublic}
                                  handleToggleAll={this.handleToggleAllSchoolTypes}/>
            }
          </div>
          {this.state.context === 'school' &&
            <div className="row">
              <GradeLevelSelector gradeLevels={this.state.gradeLevels}
                                  allGradeLevels={this.state.allGradeLevels}
                                  handleSelect={this.handleSelectGradeLevels}
                                  handleChangeAllGradeLevels={this.handleChangeAllGradeLevels}
                                  handleToggleAll={this.handleToggleAllGradeLevels}/>
            </div>
          }
          {this.state.context &&
            <MetricSelector context={this.state.context}
                            handleSelectMetric={this.handleSelectMetric}
                            handleUnselectMetric={this.handleUnselectMetric}
                            handleClearMetrics={this.handleClearMetrics} />
          }
          {this.state.criteria.length > 0 &&
            <section id="criteria">
              <h3>
                Selected criteria
              </h3>
              <table className="table table-striped">
                <tbody>
                  {this.state.criteria.map((criterion) => {
                    return (
                      <Criterion key={criterion.metric.metricId}
                                 name={criterion.metric.name}
                                 metricId={criterion.metric.metricId}
                                 onRemove={() => {
                                   return this.handleRemoveCriterion(
                                       criterion.metric.metricId
                                   );
                                 }}>
                      </Criterion>
                    );
                  })}
                </tbody>
              </table>
            </section>
          }
          <Button color="primary" onClick={this.handleSubmit}
                  disabled={this.state.loadingRankings}>
            Submit
          </Button>
          {this.state.loadingRankings &&
            <img src="/jstree/themes/default/throbber.gif" alt="Loading..."
                 className="loading"/>
          }
        </form>
        {this.state.loadingRankings &&
          <ProgressBar percent={this.state.progressPercent}
                       status={this.state.progressStatus} />
        }
        {this.state.results &&
          <RankingResults results={this.state.results}
                          criteria={this.state.criteria}
                          context={this.state.context} />
        }
        {this.state.results && this.state.noDataResults && this.state.noDataResults.length > 0 &&
          <NoDataResults results={this.state.noDataResults}
                         context={this.state.context}
                         hasResultsWithData={this.state.results && this.state.results.length > 0}
                         criteriaCount={this.state.criteria.length} />
        }
      </div>
    );
  }
}

export {FormulaForm};

ReactDom.render(
  <FormulaForm/>,
  document.getElementById('formula-form')
);
