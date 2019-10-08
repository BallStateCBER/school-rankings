import React from 'react';
import PropTypes from 'prop-types';

const Checkbox = ({type = 'checkbox', name, checked = false, onChange, id}) => (
  <input type={type} name={name} checked={checked} onChange={onChange} className="form-check-input" id={id} />
);

Checkbox.propTypes = {
  checked: PropTypes.bool,
  id: PropTypes.string.isRequired,
  name: PropTypes.string.isRequired,
  onChange: PropTypes.func.isRequired,
  type: PropTypes.string,
};

export default Checkbox;
