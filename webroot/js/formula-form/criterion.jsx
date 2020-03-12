import React from 'react';
import PropTypes from 'prop-types';
import InputRange from 'react-input-range';
import 'react-input-range/lib/css/index.css';

class Criterion extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      value: this.props.weight,
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

  handleChangeWeight(weight) {
    // Enforce upper and lower bounds
    weight = Math.max(1, weight);
    weight = Math.min(200, weight);

    this.setState({value: weight});
    this.props.handleChangeWeight(this.props.metricId, weight);
  }

  render() {
    const numericInputElementId = 'numeric-weight-input-' + this.props.metricId;

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
            <div className="col numeric-input text-right">
              <div className="form-inline">
                <div className="input-group">
                  <label htmlFor={numericInputElementId}>
                    Importance:
                  </label>
                  <input className="form-control" aria-label="Importance (from 1 to 200 percent)" type="number" min="1"
                         max="200" id={numericInputElementId} value={this.state.value}
                         onChange={
                           (element) => this.handleChangeWeight(element.target.value)
                         } />
                  <div className="input-group-append">
                    <span className="input-group-text">%</span>
                  </div>
                </div>
              </div>
            </div>
            <div className="col-8">
              <InputRange minValue={1} maxValue={200} value={this.state.value}
                          formatLabel={(value) => this.getWeightLabel(value)}
                          onChange={(value) => this.handleChangeWeight(value)} />
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
  handleChangeWeight: PropTypes.func.isRequired,
  metricId: PropTypes.number.isRequired,
  name: PropTypes.string.isRequired,
  onRemove: PropTypes.func.isRequired,
  weight: PropTypes.number.isRequired,
};

export {Criterion};
