import React from 'react';
import PropTypes from 'prop-types';

class Criterion extends React.Component {
  constructor(props) {
    super(props);

    this.state = {};
  }

  render() {
    return (
        <tr>
          <td>
            <button type="button" className="close float-right"
                    aria-label="Close" onClick={this.props.onRemove}>
              <span aria-hidden="true">&times;</span>
            </button>
            <p className="metric-name">
              {this.props.name}
            </p>
            <input type="hidden"
                   name={'criteria[' + this.props.metricId + '][metricId]'}
                   data-field="metricId"
                   value={this.props.metricId} />
          </td>
        </tr>
    );
  }
}

Criterion.propTypes = {
  metricId: PropTypes.number.isRequired,
  name: PropTypes.string.isRequired,
  onRemove: PropTypes.func.isRequired,
};

export {Criterion};
