import React from 'react';
import PropTypes from 'prop-types';
import Checkbox from './checkbox.jsx';

class CheckboxContainer extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      checkedItems: new Map(),
    };

    for (let i = 0; i < this.props.checkboxes.length; i++) {
      const item = this.props.checkboxes[i];
      this.state.checkedItems.set(item, item.checked);
    }

    this.handleChange = this.handleChange.bind(this);
  }

  handleChange(e) {
    const item = e.target.name;
    const isChecked = e.target.checked;
    this.setState((prevState) => ({
      checkedItems: prevState.checkedItems.set(item, isChecked),
    }));
    this.props.handleChange(e);
  }

  render() {
    return (
      <React.Fragment>
        {
          this.props.checkboxes.map((item) => (
              <div className="form-check" key={item.key}>
                <Checkbox name={item.name}
                          checked={this.state.checkedItems.get(item.name)}
                          onChange={this.handleChange} id={item.key} />
                <label className="form-check-label" htmlFor={item.key}>
                  {item.label}
                </label>
              </div>
          ))
        }
      </React.Fragment>
    );
  }
}

CheckboxContainer.propTypes = {
  checkboxes: PropTypes.array.isRequired,
  handleChange: PropTypes.func.isRequired,
};

export default CheckboxContainer;
