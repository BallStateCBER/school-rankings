import React from 'react';
import PropTypes from 'prop-types';
import {ResultSubject} from './result-subject.jsx';

class NoDataResults extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      showResults: false,
    };
    this.handleShowResults = this.handleShowResults.bind(this);
  }

  handleShowResults() {
    this.setState({showResults: !this.state.showResults});
  }

  /**
   * Capitalizes the provided string
   *
   * @param {string} str
   * @return {string}
   */
  static capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  render() {
    const resultsCount = this.props.results.length;
    const isPlural = resultsCount !== 1;
    const subject = this.props.context + (isPlural ? 's' : '');
    const rows = [];

    for (let i = 0; i < resultsCount; i++) {
      const subject = this.props.results[i];
      let subjectData = null;
      const contextKey = this.props.context === 'school' ? 'school' : 'school_district';
      if (subject.hasOwnProperty(contextKey)) {
        subjectData = subject[contextKey];
      } else {
        console.log('Error: ' + contextKey + ' information missing from no-data result');
        return;
      }

      rows.push(
        <tr key={'no-data-result-' + i}>
          <ResultSubject subjectData={subjectData}
                         dataCompleteness={subject.data_completeness}
                         statistics={[]}
                         criteria={[]}
                         context={this.props.context}
                         showStatistics={false} />
        </tr>
      );
    }

    return (
      <section>
        <h3>
          {resultsCount}
          {' Hidden '}
          {NoDataResults.capitalize(subject)}
        </h3>
        <p>
          {resultsCount + ' '}
          {this.props.hasResultsWithData && 'more '}
          {subject + ' '}
          in this county cannot be ranked because
          {isPlural ? ' they have ' : ' it has '}
          no data available for the selected criteria.
        </p>
        <button id="show-results-without-data"
                className="btn btn-outline-primary"
                onClick={this.handleShowResults}>
          Show {subject}
        </button>
        {this.state.showResults &&
          <table className="table ranking-results">
            <tbody>
              {rows}
            </tbody>
          </table>
        }
      </section>
    );
  }
}

NoDataResults.propTypes = {
  context: PropTypes.string.isRequired,
  hasResultsWithData: PropTypes.bool.isRequired,
  results: PropTypes.array.isRequired,
};

export {NoDataResults};
