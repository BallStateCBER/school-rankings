import React from 'react';
import PropTypes from 'prop-types';

class ProgressBar extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
    const percent = this.props.percent ? this.props.percent : 0;
    const status = this.props.status ? this.props.status : 'Starting...';

    return (
      <div className="progress-bar-container">
        <p className="status">
          {status}
        </p>
        <div className="progress">
          <div className=
                   "progress-bar progress-bar-striped progress-bar-animated"
               role="progressbar" style={{width: percent + '%'}}
               aria-valuenow={percent} aria-valuemin="0"
               aria-valuemax="100">
            {percent}%
          </div>
        </div>
      </div>
    );
  }
}

ProgressBar.propTypes = {
  percent: PropTypes.number,
  status: PropTypes.string,
};

export {ProgressBar};
