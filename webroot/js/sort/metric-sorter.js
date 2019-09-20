/**
 * This class is used as part of configuring jsTree to display a properly-ordered list of metrics
 */
class MetricSorter {
  /**
   * Returns 1 if node1 should be placed after node2, or -1 otherwise
   * @param {Object} jstree
   * @param {Object} node1
   * @param {Object} node2
   * @return {number}
   */
  static sort(jstree, node1, node2) {
    const metric1 = jstree.get_node(node1).text;
    const metric2 = jstree.get_node(node2).text;

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
  }
}

export {MetricSorter};
