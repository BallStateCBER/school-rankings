import '../../css/formula-form.scss';
import Cookies from 'universal-cookie';
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
import {ProgressBar} from './progress-bar.jsx';
import {SchoolTypeSelector} from './selectors/school-type-selector.jsx';
import {Survey} from './survey.jsx';

class FormulaForm extends React.Component {
  constructor(props) {
    super(props);
    this.adminEmail = 'admin@indianaschoolrankings.com';
    this.formulaId = null;
    this.rankingHash = null;
    this.jobId = null;
    this.state = {
      allGradeLevels: true,
      context: null,
      county: null,
      criteria: new Map(),
      demoChoice: null,
      demoFillIn: '',
      gradeLevels: new Map(),
      loadingRankings: false,
      noDataResults: null,
      onlyPublic: true,
      passesValidation: false,
      progressPercent: null,
      progressStatus: null,
      rankingUrl: null,
      resultsError: false,
      schoolTypes: new Map(),
      uuid: FormulaForm.getUuid(),
      weightInterfaceIsOpen: false,
    };
    this.submittedData = {
      context: null,
      countyId: null,
      criteria: new Map(),
      gradeIds: [],
      onlyPublic: null,
      schoolTypeIds: [],
    };
    this.debug = false;
    this.cookies = new Cookies();
    this.addRankingHashToCurrentUrl = this.addRankingHashToCurrentUrl.bind(this);
    this.getSelectedCriteriaHeader = this.getSelectedCriteriaHeader.bind(this);
    this.handleChange = this.handleChange.bind(this);
    this.handleChangeAllGradeLevelsOption = this.handleChangeAllGradeLevelsOption.bind(this);
    this.handleChangeContext = this.handleChangeContext.bind(this);
    this.handleChangeCriterionWeight = this.handleChangeCriterionWeight.bind(this);
    this.handleChangeOnlyPublic = this.handleChangeOnlyPublic.bind(this);
    this.handleClearMetrics = this.handleClearMetrics.bind(this);
    this.handleDemoFillInChange = this.handleDemoFillInChange.bind(this);
    this.handleDemoFillInFocus = this.handleDemoFillInFocus.bind(this);
    this.handleDemoRadioChange = this.handleDemoRadioChange.bind(this);
    this.handleRemoveCriterion = this.handleRemoveCriterion.bind(this);
    this.handleSelectCounty = this.handleSelectCounty.bind(this);
    this.handleSelectGradeLevels = this.handleSelectGradeLevels.bind(this);
    this.handleSelectMetric = this.handleSelectMetric.bind(this);
    this.handleSelectSchoolTypes = this.handleSelectSchoolTypes.bind(this);
    this.handleSubmit = this.handleSubmit.bind(this);
    this.handleToggleAllGradeLevels = this.handleToggleAllGradeLevels.bind(this);
    this.handleToggleAllSchoolTypes = this.handleToggleAllSchoolTypes.bind(this);
    this.handleToggleWeightInterface = this.handleToggleWeightInterface.bind(this);
    this.handleUnselectMetric = this.handleUnselectMetric.bind(this);
  }

  componentDidMount() {
    this.setSchoolTypes();
    this.setGradeLevels();
    this.autoFillDemoForm();
    this.autoFillForm();
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
      resultsError: false,
    });

    this.processForm();
  }

  /**
   * Updates this.submittedData, used to keep track of submitted data separately from the form's current state
   */
  updateSubmittedData() {
    this.submittedData.context = this.state.context;
    this.submittedData.countyId = this.state.county.value;
    this.submittedData.criteria = this.state.criteria;
    this.submittedData.gradeIds = this.getSelectedGradeIds();
    this.submittedData.onlyPublic = this.state.onlyPublic;
    this.submittedData.schoolTypeIds = this.getSelectedSchoolTypes();
  }

  processForm() {
    this.updateSubmittedData();

    return $.ajax({
      method: 'POST',
      url: '/api/formulas/add/',
      dataType: 'json',
      data: {
        context: this.submittedData.context,
        criteria: Array.from(this.submittedData.criteria.values()),
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
        progressStatus: 'Processing ranking formula',
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
        progressStatus: this.submittedData.context === 'school' ?
          'Processing school parameters' :
          'Processing school corporation parameters',
      });

      this.rankingHash = data.rankingHash;
      this.addRankingHashToCurrentUrl();
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
      !data.hasOwnProperty('rankingHash') ||
      !data.hasOwnProperty('jobId') ||
      !data.success ||
      !data.rankingHash ||
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
    if (criteria.size === 0) {
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
      id: selected.node.data.metricId,
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
    criteria.set(metric.id, criterion);
    this.setState({criteria: criteria});
  }

  handleUnselectMetric(node, selected) {
    const criteria = this.state.criteria;
    const unselectedMetricId = selected.node.data.metricId;
    criteria.delete(unselectedMetricId);
    this.setState({criteria: criteria});
  }

  handleClearMetrics() {
    this.setState({criteria: new Map()});
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
      this.submitAnalyticsEvents();
      if (data.hasOwnProperty('rankingUrl') && data.rankingUrl) {
        window.location.href = data.rankingUrl;
      } else {
        this.setState({
          loadingRankings: false,
          resultsError: true,
        });
      }
    }).fail((jqXHR) => {
      FormulaForm.logApiError(jqXHR);
      this.setState({
        loadingRankings: false,
        resultsError: true,
      });
    });
  }

  submitAnalyticsEvents() {
    const analytics = new Analytics(this);
    analytics.sendRankingPoolAnalyticsEvent();
    analytics.sendRankingCriteriaAnalyticsEvents();
  }

  handleRemoveCriterion(metricId) {
    const criteria = this.state.criteria;
    criteria.delete(metricId);
    this.setState({criteria: criteria});
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
      const schoolTypesArray = window.formulaForm.schoolTypes;
      const schoolTypesMap = new Map(schoolTypesArray.map((schoolTypes) => [schoolTypes.name, schoolTypes]));
      const publicSchoolType = schoolTypesMap.get('public');
      return [publicSchoolType.id];
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
      criteria: new Map(),
    });
  }

  handleDemoRadioChange(value) {
    this.setState({demoChoice: value});
    this.cookies.set('demoChoice', value);
  }

  handleDemoFillInChange(value) {
    this.setState({demoFillIn: value});
    this.cookies.set('demoFillIn', value);
  }

  handleDemoFillInFocus() {
    this.setState({demoChoice: 'Other'});
    this.cookies.set('demoChoice', 'Other');
  }

  /**
   * Automatically fills out the survey form using Cookie data
   */
  autoFillDemoForm() {
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

  handleChangeCriterionWeight(metricId, newWeight) {
    const criteria = this.state.criteria;
    criteria.forEach((criterion, key) => {
      if (key === metricId) {
        criterion.weight = newWeight;
        criteria.set(metricId, criterion);
      }
    });
    this.setState({criteria: criteria});
  }

  /**
   * Returns a header element for the "N criteria selected" section (#criteria)
   *
   * @param {int} criteriaCount
   * @return {*}
   */
  getSelectedCriteriaHeader(criteriaCount) {
    let headerText;
    if (criteriaCount === 0) {
      headerText = 'No criteria selected';
    } else {
      headerText = criteriaCount + (criteriaCount === 1 ? ' criterion selected' : ' criteria selected');
    }

    return (
      <h3>
        {headerText}
      </h3>
    );
  }

  /**
   * Toggles the visibility of the part of the form for changing criteria weights
   */
  handleToggleWeightInterface() {
    const weightInterfaceIsOpen = this.state.weightInterfaceIsOpen;
    this.setState({weightInterfaceIsOpen: !weightInterfaceIsOpen});
  }

  /**
   * Returns the ID of the "public" school type
   *
   * @return {number}
   */
  getPublicSchoolTypeId() {
    return 1;
  }

  /**
   * Returns the value of the 'r' query string variable, which is expected to be the ranking string hash is it exists
   *
   * @return {string}
   */
  getRankingHashFromQueryString() {
    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);

    return urlParams.get('r');
  }

  autoFillForm() {
    const rankingHash = this.getRankingHashFromQueryString();
    if (!rankingHash) {
      return;
    }

    $.ajax({
      method: 'GET',
      url: '/api/rankings/get/' + rankingHash + '.json',
      dataType: 'json',
    }).done((data) => {
      if (data && !data.hasOwnProperty('results')) {
        console.log('Error retrieving ranking results');
        console.log(data);
        return;
      }

      const input = data.inputSummary;
      const gradeLevels = this.state.gradeLevels;
      gradeLevels.forEach((gradeLevel, gradeLevelId) => {
        gradeLevel.checked = input.gradeIds.indexOf(parseInt(gradeLevelId)) !== -1;
      });

      const schoolTypes = this.state.schoolTypes;
      schoolTypes.forEach((schoolType, schoolTypeId) => {
        schoolType.checked = input.schoolTypeIds.indexOf(parseInt(schoolTypeId)) !== -1;
      });

      const totalGradeLevelCount = window.formulaForm.gradeLevels.length;
      const selectedGradeLevelCount = input.gradeIds.length;

      this.setState({
        allGradeLevels: selectedGradeLevelCount === 0 || selectedGradeLevelCount === totalGradeLevelCount,
        context: input.context,
        county: {
          label: input.counties[0].name,
          value: input.counties[0].id,
        },
        criteria: new Map(input.criteria.map((criterion) => [criterion.metric.id, criterion])),
        gradeLevels: gradeLevels,
        onlyPublic: input.schoolTypeIds.length === 1 && input.schoolTypeIds[0] === this.getPublicSchoolTypeId(),
        schoolTypes: schoolTypes,
      });

      $('#jstree').on('ready.jstree', () => {
        const jstree = $('#jstree').jstree(true);
        input.criteria.map((criterion) => {
          // Open metric groups
          if (criterion.metric.path.length > 1) {
            for (let i = 0; i < criterion.metric.path.length - 1; i++) {
              const parentMetricId = criterion.metric.path[i].id;
              jstree.open_node('li[data-metric-id=' + parentMetricId + ']');
            }
          }

          // Select nodes
          jstree.select_node('li[data-metric-id=' + criterion.metric.id + ']');
        });
      });
    });
  }

  /**
   * Adds ?r={rankingHash} to the current URL
   */
  addRankingHashToCurrentUrl() {
    const url = new URL(window.location.href);
    url.searchParams.append('r', this.rankingHash);
    window.history.replaceState(null, '', url.toString());
  }

  render() {
    return (
      <div>
        <div className="formula-form-group">
          <Survey handleRadioChange={this.handleDemoRadioChange} handleFillInFocus={this.handleDemoFillInFocus}
                  handleFillInChange={this.handleDemoFillInChange} choice={this.state.demoChoice}
                  fillIn={this.state.demoFillIn} />
        </div>
        <form onSubmit={this.handleSubmit}>
          <div id="formula-section-what" className="formula-form-group">
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
                    {this.state.context === 'school' ? 'Schools' : 'School corporations'} in which county?
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
                    Schools teaching which grades?
                  </h3>
                  <GradeLevelSelector gradeLevels={this.state.gradeLevels}
                                      allGradeLevels={this.state.allGradeLevels}
                                      handleSelect={this.handleSelectGradeLevels}
                                      handleChangeAllGradeLevels={this.handleChangeAllGradeLevelsOption}
                                      handleToggleAll={this.handleToggleAllGradeLevels}/>
                </section>
              </div>
            }
          </div>
          {this.state.context &&
            <section className="formula-form-group">
              <h3>
                How would you like the results to be ranked?
              </h3>
              <MetricSelector context={this.state.context}
                              handleSelectMetric={this.handleSelectMetric}
                              handleUnselectMetric={this.handleUnselectMetric}
                              handleClearMetrics={this.handleClearMetrics} />
            </section>
          }
          {this.state.criteria.size > 0 &&
            <section id="criteria" className="formula-form-group">
              {this.getSelectedCriteriaHeader(this.state.criteria.size)}
              <Button id="toggle-weight-table" color="secondary"
                      onClick={this.handleToggleWeightInterface}>
                {this.state.weightInterfaceIsOpen ? 'Hide' : 'Edit'} advanced settings
              </Button>
              {this.state.weightInterfaceIsOpen &&
                <table className="table table-striped">
                  <tbody>
                    {Array.from(this.state.criteria.values()).map((criterion) => {
                      const metricId = criterion.metric.id;
                      return (
                        <Criterion key={metricId} name={criterion.metric.name} metricId={metricId}
                                   weight={criterion.weight}
                                   handleChangeWeight={this.handleChangeCriterionWeight}
                                   onRemove={() => this.handleRemoveCriterion(metricId)}>
                        </Criterion>
                      );
                    })}
                  </tbody>
                </table>
              }
            </section>
          }
          <div className="formula-form-group">
            <Button color="primary" onClick={this.handleSubmit}
                    disabled={this.state.loadingRankings}>
              Submit
            </Button>
          </div>
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
      </div>
    );
  }
}

export {FormulaForm};

ReactDom.render(
  <FormulaForm/>,
  document.getElementById('formula-form')
);
