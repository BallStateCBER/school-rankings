import ReactDom from 'react-dom';
import React from 'react';
import {Button, Modal, ModalHeader, ModalBody} from 'reactstrap';
import {InputSummary} from './input-summary.jsx';
import {NoDataResults} from './no-data-results.jsx';
import {ResultSubject} from './result-subject.jsx';

class RankingResults extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      createdDate: null,
      formUrl: null,
      inputSummary: null,
      loading: true,
      noDataResults: null,
      rankingUrl: null,
      rankingUrlCopied: null,
      results: null,
      resultsError: false,
      showAllStatistics: false,
      showShareModal: false,
    };

    this.contextIsSchool = this.contextIsSchool.bind(this);
    this.copyShareUrl = this.copyShareUrl.bind(this);
    this.getShareModal = this.getShareModal.bind(this);
    this.loadResults = this.loadResults.bind(this);
    this.renderNoDataResults = this.renderNoDataResults.bind(this);
    this.renderNoResults = this.renderNoResults.bind(this);
    this.renderResults = this.renderResults.bind(this);
    this.toggleShareModal = this.toggleShareModal.bind(this);
    this.toggleShowAllStatistics = this.toggleShowAllStatistics.bind(this);

    this.loadResults();
  }

  /**
   * Calls /api/rankings/get and sets the results in the state
   */
  loadResults() {
    const resultsContainer = document.getElementById('ranking-results');
    const rankingHash = resultsContainer.dataset.rankingHash;

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

      this.setState({
        createdDate: data.createdDate,
        formUrl: data.formUrl,
        inputSummary: data.inputSummary,
        noDataResults: data.noDataResults,
        rankingUrl: data.rankingUrl,
        results: data.results,
      });
    }).fail((jqXHR) => {
      RankingResults.logApiError(jqXHR);
      this.setState({resultsError: true});
    }).always(() => {
      this.setState({loading: false});
    });
  }

  /**
   * Displays error information in the browser console
   *
   * @param {object} jqXHR
   */
  static logApiError(jqXHR) {
    let errorMsg = 'Error loading rankings';
    if (jqXHR && jqXHR.hasOwnProperty('responseJSON')) {
      if (jqXHR.responseJSON.hasOwnProperty('message')) {
        errorMsg = jqXHR.responseJSON.message;
      }
    }
    console.log('Error: ' + errorMsg);
  }

  /**
   * Returns a <ResultSubject> element
   *
   * @param {object} subject
   * @return {ResultSubject|null}
   */
  getResultCell(subject) {
    let context = null;
    let subjectData = null;
    if (subject.hasOwnProperty('school')) {
      context = 'school';
      subjectData = subject.school;
    } else if (subject.hasOwnProperty('school_district')) {
      context = 'district';
      subjectData = subject.school_district;
    } else {
      console.log('Error: Neither school nor school district found in result');

      return null;
    }

    return <ResultSubject subjectData={subjectData}
                          dataCompleteness={subject.data_completeness}
                          statistics={subject.statistics}
                          criteria={this.state.inputSummary.criteria}
                          context={context}
                          showStatistics={this.state.showAllStatistics} />;
  }

  /**
   * Toggles showing statistics for all ranking subjects
   */
  toggleShowAllStatistics() {
    this.setState({showAllStatistics: !this.state.showAllStatistics});
  }

  /**
   * Returns TRUE if the currently selected context is 'school' instead of 'school corporations'
   *
   * @return {boolean}
   */
  contextIsSchool() {
    return this.state.inputSummary.context === 'school';
  }

  /**
   * Returns the content to render when the results for this ranking request are being fetched from the API
   *
   * @return {*}
   */
  renderLoading() {
    return (
      <p>
        Loading...
      </p>
    );
  }

  /**
   * Returns the content to render when there are no results for this ranking request
   *
   * @return {*}
   */
  renderNoResults() {
    const subjectsNotFound = this.contextIsSchool() ?
      'schools' :
      'school corporations';

    return (
      <div>
        <h3>
          No Results
        </h3>
        <p>
          No {subjectsNotFound} were found with data matching your selected criteria.
        </p>
      </div>
    );
  }

  /**
   * Returns the content to render when there are results for this ranking request
   *
   * @return {*}
   */
  renderResults() {
    const rankRows = [];
    const resultsCount = this.state.results ? this.state.results.length : 0;

    for (let i = 0; i < resultsCount; i++) {
      const rank = this.state.results[i];
      rankRows.push(
        <tr key={rank.rank + '-0'}>
          <th rowSpan={rank.subjects.length} className="rank-number">
            {rank.rank}
          </th>
          {this.getResultCell(rank.subjects[0])}
        </tr>
      );
      for (let k = 1; k < rank.subjects.length; k++) {
        rankRows.push(
          <tr key={rank.rank + '-' + k}>
            {this.getResultCell(rank.subjects[k])}
          </tr>
        );
      }
    }

    const countHeader = resultsCount + ' Result' + (resultsCount > 1 ? 's' : '');
    const subjectHeader = this.contextIsSchool() ?
      'School' :
      'School Corporation';

    return (
      <div>
        <p>
          Generated on {this.state.createdDate}
        </p>
        <InputSummary submittedData={this.state.inputSummary} editUrl={this.state.formUrl} />
        <h3>
          {countHeader}
          <Button color="primary" size="sm" onClick={this.toggleShareModal} id="share-ranking-results-btn">
            Save or share these results
          </Button>
        </h3>
        <table className="table ranking-results">
          <thead>
            <tr>
              <th>
                Rank
              </th>
              <th>
                <div className="row">
                  <div className="col-lg-6">
                    {subjectHeader}
                  </div>
                  <div className="col-lg-6 d-none d-lg-block">
                    Statistics
                    <Button color="link" size="sm" onClick={this.toggleShowAllStatistics}>
                      {this.state.showAllStatistics ? 'Hide' : 'Show'} All
                    </Button>
                  </div>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            {rankRows}
          </tbody>
        </table>
        {this.getShareModal()}
      </div>
    );
  }

  toggleShareModal() {
    const showShareModal = this.state.showShareModal;
    this.setState({showShareModal: !showShareModal});
  }

  getShareModal() {
    return (
      <Modal isOpen={this.state.showShareModal} toggle={this.toggleShareModal}>
        <ModalHeader>
          Save or share the link to these rankings
        </ModalHeader>
        <ModalBody>
          <div className="input-group">
            <input type="text" value={this.state.rankingUrl} className="form-control" id="share-ranking-results-url"
                   readOnly={true} />
            <div className="input-group-append" title="Copy to clipboard" onClick={this.copyShareUrl}>
              <span className="input-group-text">
                <i className="fas fa-copy"></i>
              </span>
            </div>
          </div>
          {this.state.rankingUrlCopied === true &&
            <p className="text-success">
              URL copied to clipboard
            </p>
          }
          {this.state.rankingUrlCopied === false &&
            <p className="text-danger">
              Error copying URL to clipboard
            </p>
          }
        </ModalBody>
      </Modal>
    );
  }

  /**
   * Copies the ranking results URL to the clipboard
   */
  copyShareUrl() {
    const element = document.getElementById('share-ranking-results-url');
    element.focus();
    element.select();
    element.blur();
    let result;
    try {
      result = document.execCommand('copy');
    } catch (err) {
      result = false;
    }
    this.setState({rankingUrlCopied: result});
  }

  /**
   * Returns the content to render when there are results for this ranking request
   *
   * @return {*}
   */
  renderNoDataResults() {
    return (
      <NoDataResults context={this.contextIsSchool() ? 'school' : 'district'}
                     hasResultsWithData={(this.state.results && this.state.results.length > 0)}
                     results={this.state.noDataResults} />
    );
  }

  /**
   * Render method
   *
   * @return {*}
   */
  render() {
    const resultsCount = this.state.results ? this.state.results.length : 0;

    return (
      <div>
        {this.state.loading &&
          this.renderLoading()
        }
        {!this.state.loading && this.state.results && resultsCount === 0 &&
          this.renderNoResults()
        }
        {!this.state.loading && resultsCount > 0 &&
          this.renderResults()
        }
        {!this.state.loading && this.state.noDataResults && this.state.noDataResults.length > 0 &&
          this.renderNoDataResults()
        }
      </div>
    );
  }
}

export {RankingResults};

ReactDom.render(
  <RankingResults/>,
  document.getElementById('ranking-results')
);
