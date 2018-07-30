import React from 'react';
import PropTypes from 'prop-types';

class SchoolResult extends React.Component {
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

SchoolResult.propTypes = {
  data: PropTypes.object.isRequired,
  dataCompleteness: PropTypes.string.isRequired,
};

export {SchoolResult};
