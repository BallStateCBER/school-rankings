import '../../css/formula-form.scss';
import React from 'react';
import ReactDom from 'react-dom';
import Select from 'react-select';
import uuidv4 from 'uuid/v4';
import {Analytics} from './analytics.js';
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
    this.adminEmail = 'admin@indianaschoolrankings.com';
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
      resultsError: false,
      schoolTypes: new Map(),
      uuid: FormulaForm.getUuid(),
    };
    this.submittedData = {
      context: null,
      criteria: [],
    };
    this.debug = false;
    this.handleChange = this.handleChange.bind(this);
    this.handleChangeAllGradeLevelsOption = this.handleChangeAllGradeLevelsOption.bind(this);
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
    if (document.getElementById('formula-form').getAttribute('data-debug')) {
      this.debug = true;
    }
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
    this.setState({county: selectedOption ? selectedOption : null});
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

  handleChangeAllGradeLevelsOption(allGradeLevels) {
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
      resultsError: false,
    });

    this.processForm();
  }

  /**
   * Updates this.submittedData, used to pass properties to RankingResults that are independent of state changes
   */
  updateSubmittedData() {
    this.submittedData.context = this.state.context;
    this.submittedData.countyId = this.state.county.value;
    this.submittedData.criteria = this.state.criteria;
  }

  processForm() {
    this.updateSubmittedData();

    return $.ajax({
      method: 'POST',
      url: '/api/formulas/add/',
      dataType: 'json',
      data: {
        context: this.submittedData.context,
        criteria: this.submittedData.criteria,
      },
    }).done((data) => {
      if (
        data && (
          !data.hasOwnProperty('success') ||
          !data.hasOwnProperty('id') ||
          !data.success
        )
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
      this.setState({
        loadingRankings: false,
        resultsError: true,
      });
    });
  }

  startRankingJob() {
    if (!this.formulaId) {
      console.log('Error: Formula ID not found');
      this.setState({loadingRankings: false});
      return;
    }

    const data = {
      countyId: this.submittedData.countyId,
      formulaId: this.formulaId,
      schoolTypes: this.getSelectedSchoolTypes(),
      gradeLevels: this.getSelectedGradeIds(),
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
      this.setState({
        loadingRankings: false,
        resultsError: true,
      });
    });
  }

  static hasErrorCreatingJob(data) {
    return !data ||
      !data.hasOwnProperty('success') ||
      !data.hasOwnProperty('rankingId') ||
      !data.hasOwnProperty('jobId') ||
      !data.success ||
      !data.rankingId ||
      !data.jobId;
  }

  static logApiError(jqXHR) {
    let errorMsg = 'Error loading rankings';
    if (jqXHR && jqXHR.hasOwnProperty('responseJSON')) {
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
        id: schoolTypeData.id.toString(),
        name: schoolTypeData.id.toString(),
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
        id: gradeLevelData.id.toString(),
        name: gradeLevelData.id.toString(),
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
      if (!this.state.allGradeLevels && this.getSelectedGradeIds().length === 0) {
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
      weight: 100,
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
        data && (
          !data.hasOwnProperty('progress') ||
          !data.hasOwnProperty('status')
        )
      ) {
        console.log('Error checking job status');
        console.log(data);
        this.setState({
          loadingRankings: false,
          resultsError: true,
        });
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
      this.setState({
        loadingRankings: false,
        resultsError: true,
      });
    });
  }

  loadResults() {
    $.ajax({
      method: 'GET',
      url: '/api/rankings/get/' + this.rankingId + '.json',
      dataType: 'json',
    }).done((data) => {
      if (data && !data.hasOwnProperty('results')) {
        console.log('Error retrieving ranking results');
        console.log(data);
        return;
      }

      this.setState({
        results: data.results,
        noDataResults: data.noDataResults,
      });
      this.submitAnalyticsEvents();
    }).fail((jqXHR) => {
      FormulaForm.logApiError(jqXHR);
      this.setState({resultsError: true});
    }).always(() => {
      this.setState({loadingRankings: false});
    });
  }

  submitAnalyticsEvents() {
    const analytics = new Analytics(this);
    analytics.sendRankingPoolAnalyticsEvent();
    analytics.sendRankingCriteriaAnalyticsEvents();
  }

  handleRemoveCriterion(metricId) {
    const filteredCriteria = this.state.criteria.filter((i) => i.metric.metricId !== metricId);
    this.setState({criteria: filteredCriteria});
    const jstree = $('#jstree').jstree(true);
    jstree.deselect_node('li[data-metric-id=' + metricId + ']');
  }

  /**
   * Returns an array of selected SchoolType IDs
   *
   * @return {[]|Array}
   */
  getSelectedSchoolTypes() {
    if (this.state.context !== 'school') {
      return [];
    }

    if (this.state.onlyPublic) {
      this.state.schoolTypes.forEach(function(schoolType) {
        if (schoolType.name === 'public') {
          return schoolType.id;
        }
      });
    }

    const selectedSchoolTypes = [];
    this.state.schoolTypes.forEach(function(schoolType) {
      if (schoolType.checked) {
        selectedSchoolTypes.push(schoolType.name); // .name is actually the schoolType's ID
      }
    });
    return selectedSchoolTypes;
  }

  /**
   * Returns an array of IDs all selected grade levels
   *
   * @return {[]|Array}
   */
  getSelectedGradeIds() {
    if (this.state.context !== 'school' || this.state.allGradeLevels) {
      return [];
    }

    const selectedGradeLevels = [];
    this.state.gradeLevels.forEach(function(gradeLevel) {
      if (gradeLevel.checked) {
        selectedGradeLevels.push(gradeLevel.name); // .name is actually the grade's ID
      }
    });
    return selectedGradeLevels;
  }

  includes(haystack, needle) {
    return haystack.indexOf(needle) !== -1;
  }

  handleChangeContext(event) {
    this.setState({
      context: event.target.value,
      criteria: [],
    });
  }

  render() {
    return (
      <div>
        <form onSubmit={this.handleSubmit}>
          <div className="row">
            <section className="form-group col-sm-6">
              <h3>
                <label>
                  What would you like to rank?
                </label>
              </h3>
              <ContextSelector context={this.state.context}
                               handleChange={this.handleChangeContext} />
            </section>
            {this.state.context &&
              <section className="col-sm-6">
                <h3>
                  {this.state.context === 'school' ? 'Schools' : 'School corporations'} in what county?
                </h3>
                <div className="form-group">
                  <label htmlFor="county" className="sr-only">
                    County
                  </label>
                  <Select name="county" id="county"
                          value={this.state.county}
                          onChange={this.handleSelectCounty}
                          options={FormulaForm.getCountyOptions()} clearable={false}
                          required={true} />
                </div>
              </section>
            }
          </div>
          {this.state.context === 'school' &&
            <div className="row">
              <section id="school-type" className="col-sm-6">
                <h3>
                  What types of schools?
                </h3>
                <SchoolTypeSelector schoolTypes={this.state.schoolTypes}
                                    onlyPublic={this.state.onlyPublic}
                                    handleSelect={this.handleSelectSchoolTypes}
                                    handleChangeOnlyPublic={this.handleChangeOnlyPublic}
                                    handleToggleAll={this.handleToggleAllSchoolTypes}/>
              </section>
              <section id="grade-level" className="form-group col-sm-6">
                <h3>
                  Schools teaching what grades?
                </h3>
                <GradeLevelSelector gradeLevels={this.state.gradeLevels}
                                    allGradeLevels={this.state.allGradeLevels}
                                    handleSelect={this.handleSelectGradeLevels}
                                    handleChangeAllGradeLevels={this.handleChangeAllGradeLevelsOption}
                                    handleToggleAll={this.handleToggleAllGradeLevels}/>
              </section>
            </div>
          }
          {this.state.context &&
            <section>
              <h3>
                How would you like the results to be ranked?
              </h3>
              <MetricSelector context={this.state.context}
                              handleSelectMetric={this.handleSelectMetric}
                              handleUnselectMetric={this.handleUnselectMetric}
                              handleClearMetrics={this.handleClearMetrics} />
            </section>
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
            <span>
              <img src="/jstree/themes/default/throbber.gif" alt="Loading..."
                   className="loading"/>
              {this.state.progressStatus}
            </span>
          }
          {this.state.resultsError &&
            <p className="alert alert-danger">
              There was an error loading your results. This might be a temporary network error. Please try again, or
              contact an administrator at {' '}
              <a href={'mailto:' + this.adminEmail + '?subject=Error loading ranking results'}>{this.adminEmail}</a>
              {' '} for assistance.
            </p>
          }
        </form>
        {this.state.loadingRankings &&
          <ProgressBar percent={this.state.progressPercent} />
        }
        {this.state.results &&
          <RankingResults results={this.state.results}
                          criteria={this.submittedData.criteria}
                          context={this.submittedData.context} />
        }
        {this.state.results && this.state.noDataResults && this.state.noDataResults.length > 0 &&
          <NoDataResults results={this.state.noDataResults}
                         context={this.submittedData.context}
                         hasResultsWithData={this.state.results && this.state.results.length > 0}
                         criteriaCount={this.submittedData.criteria.length} />
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
