package com.kyusui.app.ui.customer.orders;

import com.kyusui.app.domain.repository.CustomerRepository;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import javax.inject.Provider;

@ScopeMetadata
@QualifierMetadata
@DaggerGenerated
@Generated(
    value = "dagger.internal.codegen.ComponentProcessor",
    comments = "https://dagger.dev"
)
@SuppressWarnings({
    "unchecked",
    "rawtypes",
    "KotlinInternal",
    "KotlinInternalInJava"
})
public final class OrderHistoryViewModel_Factory implements Factory<OrderHistoryViewModel> {
  private final Provider<CustomerRepository> customerRepositoryProvider;

  public OrderHistoryViewModel_Factory(Provider<CustomerRepository> customerRepositoryProvider) {
    this.customerRepositoryProvider = customerRepositoryProvider;
  }

  @Override
  public OrderHistoryViewModel get() {
    return newInstance(customerRepositoryProvider.get());
  }

  public static OrderHistoryViewModel_Factory create(
      Provider<CustomerRepository> customerRepositoryProvider) {
    return new OrderHistoryViewModel_Factory(customerRepositoryProvider);
  }

  public static OrderHistoryViewModel newInstance(CustomerRepository customerRepository) {
    return new OrderHistoryViewModel(customerRepository);
  }
}
