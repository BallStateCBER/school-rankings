import React from 'react';
import PropTypes from 'prop-types';

class Criterion extends React.Component {
  constructor(props) {
    super(props);

    this.state = {};
  }

  render() {
    return (
        <div>
          {this.props.name}
          <input type="hidden"
                 name={'criteria[' + this.props.metricId + '][metricId]'}
                 data-field="metricId"
                 value={this.props.metricId} />
        </div>
    );
  }
}

Criterion.propTypes = {
  metricId: PropTypes.number.isRequired,
  name: PropTypes.string.isRequired,
};

export {Criterion};
