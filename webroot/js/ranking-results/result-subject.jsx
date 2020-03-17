import PropTypes from 'prop-types';
import React from 'react';
import {Button} from 'reactstrap';
import {fas} from '@fortawesome/free-solid-svg-icons';
import {library, dom} from '@fortawesome/fontawesome-svg-core';

// FontAwesome
library.add(fas);
dom.watch();

class ResultSubject extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      showStatistics: props.showStatistics,
      showUnknownDataInfo: false,
    };
    this.getUnknownDataInfoText = this.getUnknownDataInfoText.bind(this);
    this.toggleShowStatistics = this.toggleShowStatistics.bind(this);
    this.toggleShowUnknownDataInfo = this.toggleShowUnknownDataInfo.bind(this);
  }

  componentDidUpdate(prevProps) {
    if (prevProps.showStatistics !== this.props.showStatistics) {
      this.setState({showStatistics: this.props.showStatistics});
    }
  }

  /**
   * Returns an element containing a warning about some stat data being unavailable, or null if no warning is necessary
   *
   * @return {null|*}
   */
  getDataCompletenessWarning() {
    const dataCompleteness = this.props.dataCompleteness;
    if (dataCompleteness !== 'partial') {
      return;
    }

    return (
      <div className="data-completeness-warning alert alert-warning">
        <p>
          Some data unavailable
          <Button color="link" onClick={this.toggleShowUnknownDataInfo}>
            <i className="fas fa-question-circle"></i>
          </Button>
        </p>
        {this.state.showUnknownDataInfo &&
          <p>
            {this.getUnknownDataInfoText()}
          </p>
        }
      </div>
    );
  }

  /**
   * Converts newline characters into '<br/>' in the provided string
   *
   * @param {string} str
   * @return {string}
   */
  static nl2br(str) {
    return str.split('\n').map((item, key) => {
      return <span key={key}>{item}<br/></span>;
    });
  }

  /**
   * Returns a <table> element with this school/district's statistics
   *
   * @param {array} statistics
   * @return {null|*}
   */
  getStatValues(statistics) {
    if (this.props.dataCompleteness === 'empty') {
      return null;
    }

    const rows = [];

    this.props.criteria.forEach((criterion, metricId) => {
      const metricName = criterion.metric.name;
      const statistic = ResultSubject.getStatistic(statistics, metricId);
      const key = this.props.subjectData.id + '-stat-' + metricId;

      rows.push(
        <tr key={key}>
          <th>
            <span className="metric-name">
              {metricName}
            </span>
          </th>
          {statistic !== false &&
            <td>
              {statistic.value}
              <span className="year">
                {statistic.year}
              </span>
            </td>
          }
          {statistic === false &&
            <td className="missing-data">
              Unknown
            </td>
          }
        </tr>
      );
    });

    return (
      <table className="statistics">
        <tbody>
          {rows}
        </tbody>
      </table>
    );
  }

  /**
   * Returns an <ul> group describing any statistics that rank 1st, 2nd, or 3rd highest in their metric groups
   *
   * @param {array} statistics
   * @return {null|*}
   */
  getRankedStats(statistics) {
    if (this.props.dataCompleteness === 'empty') {
      return null;
    }

    const rows = [];

    this.props.criteria.forEach((criterion) => {
      const metricId = criterion.metric.id;
      const statistic = ResultSubject.getStatistic(statistics, metricId);
      if (!statistic.rank) {
        return null;
      }

      const key = this.props.subjectData.id + '-stat-rank-' + metricId;
      let displayedRank = null;
      switch (statistic.rank) {
        case 1:
          displayedRank = statistic.rankTied ?
            <span>Tied for highest score for</span> :
            <span>Highest score</span>;
          break;
        case 2:
          displayedRank = statistic.rankTied ?
            <span>Tied for 2<sup>nd</sup> highest score</span> :
            <span>2<sup>nd</sup> highest score</span>;
          break;
        case 3:
          displayedRank = statistic.rankTied ?
            <span>Tied for 3<sup>rd</sup> highest score</span> :
            <span>3<sup>rd</sup> highest score</span>;
          break;
        default:
          return;
      }

      const metricName = criterion.metric.name;
      rows.push(
        <li key={key} className={'stat-ranked stat-ranked-' + statistic.rank}>
          <i className="fas fa-trophy"></i>
          {displayedRank}
          <span className="metric-name">
            {metricName}
          </span>
        </li>
      );
    });

    if (rows.length === 0) {
      return null;
    }

    return (
      <ul className="stats-ranked">
        {rows}
      </ul>
    );
  }

  /**
   * Returns the statistic value for the specified metric and this school/district, or FALSE if it can't be found
   *
   * @param {array} statistics
   * @param {int} metricId
   * @return {boolean|string}
   */
  static getStatistic(statistics, metricId) {
    for (let i = 0; i < statistics.length; i++) {
      if (statistics[i].metric_id === metricId) {
        return statistics[i];
      }
    }

    return false;
  }

  /**
   * Capitalizes the first character of the supplied string
   *
   * @param {string} string
   * @return {string}
   */
  capitalize(string) {
    if (typeof string !== 'string') {
      return string;
    }
    return string.charAt(0).toUpperCase() + string.slice(1);
  }

  /**
   * Takes the current school's array of grade levels and returns a string describing them
   *
   * @return {string}
   */
  getDisplayedGradeLevels() {
    const gradeLevels = this.props.subjectData.grades;
    if (!Array.isArray(gradeLevels) || gradeLevels.length === 0) {
      return '';
    }

    // Collect numbered grades (e.g. Grade 10) and non-numbered grades (e.g. Kindergarten) separately
    const namedGradeLevels = [];
    const numberedGradeLevels = [];
    for (let n = 0; n < gradeLevels.length; n++) {
      const name = gradeLevels[n].name.toLowerCase();
      if (name.search('grade') === -1) {
        namedGradeLevels.push(name);
        continue;
      }
      const gradeNumber = name.replace('grade ', '');
      numberedGradeLevels.push(gradeNumber);
    }

    // Shorten a group of grades (9, 10, 11, 12) into a range (9-12), assuming that no school skips any grades
    const retval = namedGradeLevels;
    if (numberedGradeLevels.length === 1) {
      retval.push('grade ' + numberedGradeLevels[0]);
    } else if (numberedGradeLevels.length > 1) {
      retval.push('grades ' + Math.min(...numberedGradeLevels) + '-' + Math.max(...numberedGradeLevels));
    }

    // Add serial commas and "and"
    if (retval.length > 1) {
      const lastIndex = retval.length - 1;
      retval[lastIndex] = 'and ' + retval[lastIndex];
    }
    const delimiter = retval.length > 2 ? ', ' : ' ';

    return ' teaching ' + retval.join(delimiter);
  }

  /**
   * Toggles the display of all statistics
   */
  toggleShowStatistics() {
    this.setState({showStatistics: !this.state.showStatistics});
  }

  /**
   * Returns a string describing what kind of school the current school is, e.g. "Public school"
   *
   * @return {string}
   */
  getDisplayedSchoolType() {
    if (this.props.subjectData.school_type.hasOwnProperty('name')) {
      return this.capitalize(this.props.subjectData.school_type.name) + ' school';
    }

    return 'School';
  }

  /**
   * Main render method
   *
   * @return {*}
   */
  render() {
    const colClassName = this.props.dataCompleteness === 'empty' ? 'col-lg-12' : 'col-lg-6';

    return (
      <td key={this.props.subjectData.id}>
        <div className="row">
          <div className={colClassName + ' school-info'}>
            <h3 className="school-name">
              {this.props.subjectData.name}
            </h3>
            {this.props.context === 'school' &&
              <p>
                {this.getDisplayedSchoolType()}
                {this.getDisplayedGradeLevels()}
              </p>
            }
            {this.props.context === 'school' && this.props.subjectData.address &&
              <p>
                {ResultSubject.nl2br(this.props.subjectData.address)}
              </p>
            }
            <p>
              {this.props.subjectData.phone &&
                <span>
                  {this.props.subjectData.phone} <br />
                </span>
              }
              {this.props.subjectData.url &&
                <a href={this.props.subjectData.url} target="_blank"
                   rel="noopener noreferrer">
                  Visit website
                </a>
              }
            </p>
          </div>
          {this.props.dataCompleteness !== 'empty' &&
            <div className="col-lg-6 school-stats">
              <h4 className="d-lg-none">
                Statistics
              </h4>
              {this.getRankedStats(this.props.statistics)}
              <Button color="secondary" size="sm" onClick={this.toggleShowStatistics}>
                {this.state.showStatistics ? 'Hide' : 'Show'} Statistics
              </Button>
              {this.state.showStatistics && this.getDataCompletenessWarning()}
              {this.state.showStatistics && this.getStatValues(this.props.statistics)}
            </div>
          }
        </div>
      </td>
    );
  }

  toggleShowUnknownDataInfo() {
    this.setState({showUnknownDataInfo: !this.state.showUnknownDataInfo});
  }

  /**
   * Returns the text that explains unknown data
   *
   * @return {string}
   */
  getUnknownDataInfoText() {
    const subjectNoun = this.props.context === 'school' ? 'school' : 'school corporation';

    return 'Some data may not be reported by a ' + subjectNoun + ' for various reasons, such as a measurement ' +
      'not relating to any of the grade levels taught by a ' + subjectNoun + ' or the data for very small student ' +
      'populations being suppressed in order to protect individual privacy.';
  };
}

ResultSubject.propTypes = {
  context: PropTypes.string.isRequired,
  criteria: PropTypes.array.isRequired,
  dataCompleteness: PropTypes.string.isRequired,
  showStatistics: PropTypes.bool.isRequired,
  statistics: PropTypes.array.isRequired,
  subjectData: PropTypes.object.isRequired,
};

export {ResultSubject};
