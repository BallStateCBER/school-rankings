import React from 'react';
import PropTypes from 'prop-types';

class DistrictResult extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
    return (
        <td key={this.props.data.id}>
          {this.props.data.name}
          <br />
          {this.props.dataCompleteness}
        </td>
    );
  }
}

DistrictResult.propTypes = {
  data: PropTypes.object.isRequired,
  dataCompleteness: PropTypes.string.isRequired,
};

export {DistrictResult};
