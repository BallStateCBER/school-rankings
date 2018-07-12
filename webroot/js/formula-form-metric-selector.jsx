import React from 'react';
import PropTypes from 'prop-types';
import {MetricFormatter} from './metric-manager-formatter';

class MetricSelector extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      errorMsg: '',
      hasError: false,
      loading: false,
    };
  }

  componentDidMount() {
    this.setState({loading: true});

    $.ajax({
      method: 'GET',
      url: '/api/metrics/' + this.props.context + 's.json?no-hidden=1',
      dataType: 'json',
    }).done((data) => {
      $('#jstree').jstree(MetricSelector.getJsTreeConfig(data));
    }).fail((jqXHR) => {
      let errorMsg = 'Error loading metrics';
      if (jqXHR.hasOwnProperty('responseJSON')) {
        if (jqXHR.responseJSON.hasOwnProperty('message')) {
          errorMsg = jqXHR.responseJSON.message;
        }
      }
      this.setState({
        hasError: true,
        errorMsg: errorMsg,
      });
    }).always(() => {
      this.setState({loading: false});
      $(document).on('dnd_stop.vakata', (event, data) => {
        this.handleNodeDrop(data);
      });
    });
  }

  static getJsTreeConfig(data) {
    return {
      'core': {
        'data': MetricFormatter.formatMetricsForJsTree(data.metrics),
        'check_callback': true,
      },
      'plugins': [
        'sort',
        'wholerow',
      ],
    };
  }

  render() {
    return (
      <div>
        {this.state.loading &&
          <span className="loading">
            Loading options...
            <img src="/jstree/themes/default/throbber.gif" alt="Loading..."
                 className="loading" />
          </span>
        }
        {this.state.hasError &&
          <p className="text-danger">{this.state.errorMsg}</p>
        }
        <div id="jstree"></div>
      </div>
    );
  }
}

MetricSelector.propTypes = {
  context: PropTypes.string.isRequired,
};

export {MetricSelector};
