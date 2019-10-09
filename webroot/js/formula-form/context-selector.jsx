import React from 'react';
import PropTypes from 'prop-types';

class ContextSelector extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
    return (
      <section className="form-group col-sm-6">
        <h3>
          <label>
            What would you like to rank?
          </label>
        </h3>
        <div className="form-check">
          <input className="form-check-input" type="radio" name="context"
                 id="context-school" value="school"
                 onChange={this.props.handleChange}
                 checked={this.props.context === 'school'} />
          <label className="form-check-label" htmlFor="context-school">
            Schools
          </label>
        </div>
        <div className="form-check">
          <input
            className="form-check-input"
            type="radio"
            name="context"
            id="context-district" value="district"
            onChange={this.props.handleChange}
            checked={this.props.context === 'district'} />
          <label className="form-check-label" htmlFor="context-district">
            School corporations (districts)
          </label>
        </div>
      </section>
    );
  }
}

ContextSelector.propTypes = {
  context: PropTypes.string,
  handleChange: PropTypes.func.isRequired,
};

export {ContextSelector};
