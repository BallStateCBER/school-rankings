import React from 'react';
import PropTypes from 'prop-types';
import {FormulaForm} from './formula-form.jsx';

class NoDataResults extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
    const resultCount = this.props.results.length;
    const isPlural = resultCount !== 1;
    const subject = this.props.context + (isPlural ? 's' : '');

    return (
      <section>
        <h3>
          {resultCount}
          {' Hidden '}
          {FormulaForm.capitalize(subject)}
        </h3>
        <p>
          {resultCount + ' '}
          {this.props.hasResultsWithData && 'more '}
          {subject}
          {isPlural ? ' were ' : ' was '}
          found in this location that
          {isPlural ? ' have ' : ' has '}
          no data available in the categories that you selected.
        </p>
      </section>
    );
  }
}

NoDataResults.propTypes = {
  context: PropTypes.string.isRequired,
  criteriaCount: PropTypes.number.isRequired,
  hasResultsWithData: PropTypes.bool.isRequired,
  results: PropTypes.array.isRequired,
};

export {NoDataResults};
