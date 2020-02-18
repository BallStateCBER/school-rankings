import React from 'react';
import PropTypes from 'prop-types';
import InputRange from 'react-input-range';
import 'react-input-range/lib/css/index.css';

class Criterion extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      value: 100,
    };
  }

  getWeightLabel(value) {
    if (value >= 80 && value <= 120) {
      return 'Normal';
    }
    if (value < 80) {
      return 'Less important';
    }
    if (value > 120) {
      return 'More important';
    }
  }

  render() {
    return (
      <tr>
        <td>
          <button type="button" className="close float-right" aria-label="Close" onClick={this.props.onRemove}>
            <span aria-hidden="true">&times;</span>
          </button>
          <p className="metric-name">
            {this.props.name}
          </p>
          <div className="row weight-input">
            <div className="col-2">
              Importance:
            </div>
            <div className="col-10">
              <InputRange minValue={1} maxValue={200} value={this.state.value}
                          formatLabel={(value) => this.getWeightLabel(value)}
                          onChange={(value) => this.setState({value})} />
            </div>
          </div>
          <input type="hidden" name={'criteria[' + this.props.metricId + '][metricId]'} data-field="metricId"
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
