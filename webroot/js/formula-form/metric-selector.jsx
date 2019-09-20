import React from 'react';
import PropTypes from 'prop-types';
import {Formatter} from '../metric-manager/formatter.js';
import {Button} from 'reactstrap';
import 'jstree';

class MetricSelector extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      errorMsg: '',
      hasError: false,
      loading: false,
      successfullyLoaded: false,
    };
    this.setupClickEvents = this.setupClickEvents.bind(this);
    this.clearMetricTree = this.clearMetricTree.bind(this);
  }

  componentDidUpdate(prevProps) {
    if (prevProps.context !== this.props.context) {
      this.clearMetricTree();
      this.loadMetricTree();
    }
  }

  componentDidMount() {
    this.loadMetricTree();
  }

  clearMetricTree() {
    this.setState({successfullyLoaded: false});
    let jstree = $('#jstree').jstree(true);
    if (jstree !== false) {
      jstree.destroy();
    }
  }

  loadMetricTree() {
    this.setState({loading: true});

    $.ajax({
      method: 'GET',
      url: '/api/metrics/' + this.props.context + 's.json?no-hidden=1',
      dataType: 'json',
    }).done((data) => {
      // Load jsTree
      let container = $('#jstree');
      container.jstree(MetricSelector.getJsTreeConfig(data));
      this.setState({successfullyLoaded: true});
      this.setupSearch();
      this.setupClickEvents();
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
    });
  }

  setupSearch() {
    let search = $('#jstree-search');
    let timeout = false;
    let container = $('#jstree');
    search.keyup(function() {
      if (timeout) {
        clearTimeout(timeout);
      }
      timeout = setTimeout(function() {
        const value = search.val();
        container.jstree(true).search(value);
      }, 250);
    });
  }

  setupClickEvents() {
    let container = $('#jstree');

    container.on('select_node.jstree', (node, selected) => {
      this.props.handleSelectMetric(node, selected);
    });

    container.on('deselect_node.jstree', (node, selected) => {
      this.props.handleUnselectMetric(node, selected);
    });
  }

  static getJsTreeConfig(data) {
    return {
      core: {
        data: Formatter.formatMetricsForJsTree(data.metrics),
        check_callback: true,
        themes: {
          icons: false,
        },
      },
      plugins: [
        'checkbox',
        'conditionalselect',
        'search',
        'sort',
        'wholerow',
      ],
      checkbox: {
        three_state: false,
        // tie_selection: false,
      },
      conditionalselect: function(node) {
        return node.data.selectable;
      },
      search: {
        show_only_matches: true,
        show_only_matches_children: true,
      },

      /**
       * Returns 1 if node1 should be placed after node2, or -1 otherwise
       * @param {Object} node1
       * @param {Object} node2
       * @return {number}
       */
      sort: function(node1, node2) {
        const metric1 = this.get_node(node1).text;
        const metric2 = this.get_node(node2).text;

        // Order grades
        const gradeKeyword = 'Grade ';
        const metric1IsGrade = metric1.indexOf(gradeKeyword) === 0;
        const metric2IsGrade = metric2.indexOf(gradeKeyword) === 0;
        if (metric1IsGrade && metric2IsGrade) {
          const grade1 = metric1.substring(gradeKeyword.length);
          const grade2 = metric2.substring(gradeKeyword.length);

          // Sort "Grade 12+..." after other "Grade..." metrics
          if (grade1.indexOf('12+') !== -1) {
            return 1;
          }
          if (grade2.indexOf('12+') !== -1) {
            return -1;
          }

          // Sort grades numerically, if possible
          const grade1Num = parseFloat(grade1);
          const grade2Num = parseFloat(grade2);
          if (!isNaN(grade1Num) && !isNaN(grade2Num)) {
            return grade1Num > grade2Num ? 1 : -1;
          }
        }

        // Order "Total..." metrics after "Grade..." metrics
        if (metric1IsGrade) {
          return metric2.indexOf('Total') !== -1 ? -1 : 1;
        }
        if (metric2IsGrade) {
          return metric1.indexOf('Total') !== -1 ? 1 : -1;
        }

        // Order "Pre-K" before "Kindergarten"
        const kgKeyword = 'Kindergarten';
        const pkKeyword = 'Pre-K';
        if (metric1.indexOf(pkKeyword) === 0 && metric2.indexOf(kgKeyword) === 0) {
          return -1;
        }
        if (metric2.indexOf(pkKeyword) === 0 && metric1.indexOf(kgKeyword) === 0) {
          return 1;
        }

        return metric1 > metric2 ? 1 : -1;
      },
    };
  }

  render() {
    return (
      <section>
        <h3>
          How would you like the results to be ranked?
        </h3>
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
        {this.state.successfullyLoaded &&
          <div className="input-group" id="jstree-search-container">
            <label htmlFor="jstree-search" className="sr-only">
              Search
            </label>
            <input type="text" className="form-control" id="jstree-search"
                   placeholder="Search..."/>
            <Button color="secondary" onClick={this.props.handleClearMetrics}>
              Clear selections
            </Button>
          </div>
        }
        <div id="jstree"></div>
      </section>
    );
  }
}

MetricSelector.propTypes = {
  context: PropTypes.string.isRequired,
  handleClearMetrics: PropTypes.func.isRequired,
  handleSelectMetric: PropTypes.func.isRequired,
  handleUnselectMetric: PropTypes.func.isRequired,
};

export {MetricSelector};
